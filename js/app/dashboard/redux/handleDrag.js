import _ from "lodash"
import { isTourAssigned, makeSelectTaskListItemsByUsername, selectAllTours, selectTaskLists, selectTourById, tourIsAssignedTo } from "../../../shared/src/logistics/redux/selectors"
import { disableDropInTours, enableDropInTours, selectAllTasks } from "../../coopcycle-frontend-js/logistics/redux"
import { clearSelectedTasks, modifyTaskList as modifyTaskListAction, modifyTour as modifyTourAction, updateTourInUI } from "./actions"
import { belongsToTour, selectGroups, selectSelectedTasks } from "./selectors"
import { withLinkedTasks } from "./utils"
import { toast } from 'react-toastify'


export function handleDragStart(result) {
  return function(dispatch, getState) {

    const selectedTasksIds = selectSelectedTasks(getState()).map(t => t['@id'])

    // If the user is starting to drag something that is not selected then we need to clear the selection.
    // https://github.com/atlassian/@hello-pangea/dnd/blob/master/docs/patterns/multi-drag.md#dragging
    const isDraggableSelected = selectedTasksIds.includes(result.draggableId)

    if (!isDraggableSelected) {
      dispatch(clearSelectedTasks())
    }

    // prevent the user to drag a tour into a tour
    if (result.draggableId.startsWith('tour:')) {
      dispatch(disableDropInTours())
    } else {
      dispatch(enableDropInTours())
    }

  }
}

export function handleDragEnd(result, modifyTaskList=modifyTaskListAction, modifyTour=modifyTourAction) {

  return function(dispatch, getState) {

    const handleDropInTaskList = (tasksList, selectedTasks, index, updateTourUI=null) => {
      let newTasksList = [...tasksList.items]


      selectedTasks.forEach((task) => {
        let taskIndex = newTasksList.findIndex((item) => item['@id'] === task['@id'])
        // if the task was already in the tasklist, remove from its original place
        if ( taskIndex > -1) {
          newTasksList.splice(taskIndex, 1)
        }
      })

      newTasksList.splice(index, 0, ...selectedTasks)
      return dispatch(modifyTaskList(tasksList.username, newTasksList, updateTourUI))
    },
    getPositionInFlatTaskList = (nestedTaskList, destinationIndex, tourId=null) => {
      if (tourId) {
        return nestedTaskList.find((tourOrTask) => tourOrTask['@id'] === tourId).items[0].position + destinationIndex
      } else if (destinationIndex == 0) {
        return 0
      } else {
        let taskListItem = nestedTaskList[destinationIndex - 1],
        position = taskListItem['@type'] === 'Tour' ? _.last(taskListItem.items).position : taskListItem.position
        return position + 1
      }
    }

    // dropped nowhere
    if (!result.destination) {
      return;
    }

    const source = result.source;
    const destination = result.destination;

    // did not move anywhere - can bail early
    if (
      source.droppableId === destination.droppableId &&
      source.index === destination.index
    ) {
      return;
    }

    // reordered inside the unassigned list or unassigned tours list, do nothing
    if (
      source.droppableId === destination.droppableId &&
      ( source.droppableId === 'unassigned' || source.droppableId === 'unassigned_tours' )
    ) {
      return;
    }

    if (source.droppableId.startsWith('assigned:') && destination.droppableId === 'unassigned') {
      toast.warn("Can not unassign by drag'n drop at the moment")
      return
    }

    if (source.droppableId.startsWith('assigned:') && destination.droppableId.startsWith('tour:')) {
      let tourId = destination.droppableId.split(':')[1],
      tour = selectTourById(getState(), tourId)

      if (!isTourAssigned(tour)) {
        toast.warn("Can not unassign by drag'n drop at the moment")
        return
      }
    }

    if (source.droppableId.startsWith('tour:') && destination.droppableId === 'unassigned') {
      toast.warn("Can not remove from tour by drag'n drop at the moment")
      return
    }

    if (source.droppableId.startsWith('tour:') && destination.droppableId.startsWith('tour:') && source.droppableId !== destination.droppableId) {
      toast.warn("Can not move directly tasks between tours at the moment")
      return
    }

    if (source.droppableId.startsWith('group:') && destination.droppableId.startsWith('group:') && source.droppableId !== destination.droppableId) {
      toast.warn("Can not move directly tasks between groups at the moment")
      return
    }

    if (source.droppableId.startsWith('tour:') && destination.droppableId.startsWith('assigned:')) {
      toast.warn("Can not move directly individual task from tour to assigned at the moment")
      return
    }

    if (source.droppableId.startsWith('group:') && destination.droppableId.startsWith('assigned:')) {
      toast.warn("Can not move directly individual task from group to assigned at the moment")
      return
    }

    const allTasks = selectAllTasks(getState())

    // FIXME : if a tour or a group is selected, selectSelectedTasks yields [ undefined ] so we test > 1 no > 0
    let selectedTasks = selectSelectedTasks(getState()).length > 1 ? selectSelectedTasks(getState()) : [_.find(allTasks, t => t['@id'] === result.draggableId)]

    // we are moving a whole group or tour, override selectedTasks
    if (result.draggableId.startsWith('group')) {
      let groupId = result.draggableId.split(':')[1]
      selectedTasks = selectGroups(getState()).find(g => g.id == groupId).tasks
    }
    else if (result.draggableId.startsWith('tour')) {
      let tourId = result.draggableId.split(':')[1],
      tour = selectTourById(getState(), tourId)
      selectedTasks = tour.items
    }

    // we want to move linked tasks together only in this case, so the dispatcher can have fine-grained control
    if (source.droppableId === 'unassigned') {
      selectedTasks =  withLinkedTasks(selectedTasks, allTasks, true)
      selectedTasks = selectedTasks.filter(
        t => !belongsToTour(t) && !t.isAssigned // these are already somewhere nice!
      )
    }

    if (selectedTasks.length === 0) return // can happen, for example dropping empty tour

    if (destination.droppableId.startsWith('tour:')) {
      const tours = selectAllTours(getState())
      var tourId = destination.droppableId.replace('tour:', '')
      const tour = tours.find(t => t['@id'] == tourId)

      var newTourItems = [ ...tour.items ]

      // Reorder tasks inside a tour -> remove tasks that were already there
      if (source.droppableId === destination.droppableId) {
        _.remove(newTourItems, t => selectedTasks.find(selectedTask => selectedTask['@id'] === t['@id']))
      }

        Array.prototype.splice.apply(newTourItems,
          Array.prototype.concat([ destination.index, 0 ], selectedTasks))

      if (isTourAssigned(tour)) {
        const tasksLists = selectTaskLists(getState())
        const username = tourIsAssignedTo(tour)
        const tasksList = _.find(tasksLists, tl => tl.username === username)
        const nestedTaskList = makeSelectTaskListItemsByUsername()(getState(), {username})
        const index = getPositionInFlatTaskList(nestedTaskList, destination.index, tourId)

        let uiUpdate = () => updateTourInUI(dispatch, tour, newTourItems)

        handleDropInTaskList(tasksList, selectedTasks, index, uiUpdate).then(() => {
          dispatch(modifyTour(tour, newTourItems, true))
        })
      } else {
        // FIXME : should unassign added tasks if needed
        dispatch(modifyTour(tour, newTourItems))
      }
    } else if (destination.droppableId.startsWith('assigned:')) {
      const tasksLists = selectTaskLists(getState())
      const username = destination.droppableId.replace('assigned:', '')
      const tasksList = _.find(tasksLists, tl => tl.username === username)
      const nestedTaskList = makeSelectTaskListItemsByUsername()(getState(), {username})
      const index = getPositionInFlatTaskList(nestedTaskList, destination.index)
      handleDropInTaskList(tasksList, selectedTasks, index)
    }
  }
}