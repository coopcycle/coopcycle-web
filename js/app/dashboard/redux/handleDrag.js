import _ from "lodash"
import { isTourAssigned, makeSelectTaskListItemsByUsername, selectTaskIdToTourIdMap, selectTasksListsWithItems, selectTourById, tourIsAssignedTo } from "../../../shared/src/logistics/redux/selectors"
import { setIsTourDragging, selectAllTasks } from "../../coopcycle-frontend-js/logistics/redux"
import { clearSelectedTasks,
  modifyTaskList as modifyTaskListAction,
  modifyTour as modifyTourAction,
  removeTasksFromTour as removeTasksFromTourAction,
  setUnassignedTasksLoading,
  unassignTasks as unassignTasksAction
} from "./actions"
import { belongsToTour, selectGroups, selectSelectedTasks } from "./selectors"
import { isValidTasksMultiSelect, withOrderTasksForDragNDrop } from "./utils"
import { toast } from 'react-toastify'
import i18next from "i18next"


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
      dispatch(setIsTourDragging(true))
    } else {
      dispatch(setIsTourDragging(false))
    }

  }
}

export function handleDragEnd(
  result,
  modifyTaskList=modifyTaskListAction,
  modifyTour=modifyTourAction,
  unassignTasks=unassignTasksAction,
  removeTasksFromTour=removeTasksFromTourAction) {
  /*
    Handle the end of drag of `result.draggableId` into `result.destination.droppableId` from `result.destination.droppableId`.

    The function is subdivided like:
      - Return early if we don't support the move
      - Find the involved tasks
      - Validate the set of tasks
      - Dispatch actions according to the destination
  */

  return  function(dispatch, getState) {

    const  handleDropInTaskList = async (tasksList, selectedTasks, index) => {
      let newTasksList = [...tasksList.items]

      selectedTasks.forEach((task) => {
        let taskIndex = newTasksList.findIndex((item) => item['@id'] === task['@id'])
        // if the task was already in the tasklist, remove from its original place
        if ( taskIndex > -1) {
          newTasksList.splice(taskIndex, 1)
        }

      })

      newTasksList.splice(index, 0, ...selectedTasks)

      if(selectedTasks[0].assignedTo && selectedTasks[0].assignedTo !== tasksList.username) {
        dispatch(setUnassignedTasksLoading(true))
        await dispatch(unassignTasks(selectedTasks[0].assignedTo, selectedTasks))
        dispatch(setUnassignedTasksLoading(false))
      }

      return dispatch(modifyTaskList(tasksList.username, newTasksList))
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

    if (source.droppableId.startsWith('group:') && destination.droppableId.startsWith('group:') && source.droppableId !== destination.droppableId) {
      toast.warn(i18next.t("ADMIN_DASHBOARD_CAN_NOT_MOVE_TASKS_BETWEEN_GROUPS"))
      return
    }

    if (source.droppableId.startsWith('group:') && destination.droppableId.startsWith('assigned:')) {
      toast.warn(i18next.t("ADMIN_DASHBOARD_CAN_NOT_MOVE_FROM_GROUP_TO_ASSIGNED"))
      return
    }

    if (result.draggableId.startsWith('group:') && destination.droppableId.startsWith('unassigned')) {
      toast.warn(i18next.t("ADMIN_DASHBOARD_GROUP_TO_UNASSIGNED"))
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

    if (selectedTasks.length === 0) return // can happen, for example dropping empty tour

    const taskIdToTourIdMap = selectTaskIdToTourIdMap(getState())

    if(!isValidTasksMultiSelect(selectedTasks, taskIdToTourIdMap)){
      toast.warn(i18next.t('ADMIN_DASHBOARD_INVALID_TASKS_SELECTION'), {autoclose: 15000})
      return
    }

    // when we drag n drop we want all tasks of the order/delivery to move alongside
    if (source.droppableId !== destination.droppableId) {
      selectedTasks =  withOrderTasksForDragNDrop(selectedTasks, allTasks, taskIdToTourIdMap)
    }

    // reordered inside the unassigned tours list
    if (
      source.droppableId === destination.droppableId && source.droppableId === 'unassigned_tours'
    ) {
      const itemId = result.draggableId.startsWith('tour:') ? result.draggableId.replace('tour:', '') : result.draggableId.replace('group:', '')
      dispatch({type: "INSERT_IN_UNASSIGNED_TOURS", itemId: itemId, index: result.destination.index})
      return;
    } else if (
      source.droppableId === destination.droppableId && source.droppableId === 'unassigned'
    ) {
      dispatch({type: "INSERT_IN_UNASSIGNED_TASKS", tasksToInsert: selectedTasks, index: result.destination.index})
      return;
    } else if (destination.droppableId === 'unassigned') {
      if (!belongsToTour(selectedTasks[0])(getState())) {
        dispatch(unassignTasks(selectedTasks[0].assignedTo, selectedTasks))
      } else {
        const tourId = selectTaskIdToTourIdMap(getState()).get(selectedTasks[0]['@id'])
        const tour = selectTourById(getState(), tourId)
        dispatch(removeTasksFromTour(tour, selectedTasks, selectedTasks[0].assignedTo)) // will unassign if assignedTo is defined
      }

    } else if (destination.droppableId === 'unassigned_tours' && result.draggableId.startsWith('tour')) {
      // unassigning a whole tour
      dispatch(unassignTasks(selectedTasks[0].assignedTo, selectedTasks))
    } else if (destination.droppableId.startsWith('tour:')) {
      var tourId = destination.droppableId.replace('tour:', '')
      const tour = selectTourById(getState(), tourId)

      var newTourItems = [ ...tour.items ]

      // Reorder tasks inside a tour
      if (source.droppableId === destination.droppableId) {
        _.remove(newTourItems, t => selectedTasks.find(selectedTask => selectedTask['@id'] === t['@id']))
      } // moving single tasks between tours
      else if (source.droppableId.startsWith('tour:')) {
        var sourceTourId = source.droppableId.replace('tour:', '')
        const sourceTour = selectTourById(getState(), sourceTourId)
        dispatch(removeTasksFromTour(sourceTour, selectedTasks))
      }

      Array.prototype.splice.apply(newTourItems, Array.prototype.concat([ destination.index, 0 ], selectedTasks))

      if (isTourAssigned(tour)) {
        const tasksLists = selectTasksListsWithItems(getState())
        const username = tourIsAssignedTo(tour)
        const tasksList = _.find(tasksLists, tl => tl.username === username)
        const nestedTaskList = makeSelectTaskListItemsByUsername()(getState(), {username})
        const index = getPositionInFlatTaskList(nestedTaskList, destination.index, tourId)

        handleDropInTaskList(tasksList, selectedTasks, index)
        dispatch(modifyTour(tour, newTourItems))
      } else {
        if (selectedTasks[0].assignedTo) {
          dispatch(unassignTasks(selectedTasks[0].assignedTo, selectedTasks))
        }
        dispatch(modifyTour(tour, newTourItems))
      }
    } else if (destination.droppableId.startsWith('assigned:')) {
      const tasksLists = selectTasksListsWithItems(getState())
      const username = destination.droppableId.replace('assigned:', '')
      const tasksList = _.find(tasksLists, tl => tl.username === username)
      const nestedTaskList = makeSelectTaskListItemsByUsername()(getState(), {username})
      const index = getPositionInFlatTaskList(nestedTaskList, destination.index)

      // moving task(s) to a tasklist but not the whole tour -> remove tasks from tour
      if (source.droppableId.startsWith('tour:')) {
        const sourceTourId = source.droppableId.replace('tour:', '')
        const sourceTour = selectTourById(getState(), sourceTourId)
        dispatch(removeTasksFromTour(sourceTour, selectedTasks))
      }

      handleDropInTaskList(tasksList, selectedTasks, index)
    }
  }
}