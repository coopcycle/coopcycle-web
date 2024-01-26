import _ from "lodash"
import { isTourAssigned, makeSelectTaskListItemsByUsername, selectAllTours, selectTaskLists, tourIsAssignedTo } from "../../../shared/src/logistics/redux/selectors"
import { selectAllTasks } from "../../coopcycle-frontend-js/logistics/redux"
import { clearSelectedTasks, modifyTour } from "./actions"
import { selectGroups, selectSelectedTasks, taskSelectors, tourSelectors } from "./selectors"
import { withLinkedTasks } from "./utils"

export function handleDragStart(result) {
  return function(dispatch, getState) {

    const selectedTasks = getState().selectedTasks

    // If the user is starting to drag something that is not selected then we need to clear the selection.
    // https://github.com/atlassian/react-beautiful-dnd/blob/master/docs/patterns/multi-drag.md#dragging
    const isDraggableSelected = selectedTasks.includes(result.draggableId)

    if (!isDraggableSelected) {
      dispatch(clearSelectedTasks())
    }

  }
}
  
// modifyTaskList is passed as argument so we can test this function thanks to dependency injection
export function handleDragEnd(result, modifyTaskList) {
  
  return function(dispatch, getState) {

    const handleDropInTour = (tour, selectedTasks, source, destination) => {
      let newTourItems = [ ...tour.items ]
    
        // Drop new tasks into existing tour
        if (source.droppableId === 'unassigned') {
          Array.prototype.splice.apply(newTourItems,
            Array.prototype.concat([ destination.index, 0 ], selectedTasks))
        }
    
        // Reorder tasks of existing tour
        if (source.droppableId === destination.droppableId) {
          const [ removed ] = newTourItems.splice(source.index, 1);
          newTourItems.splice(destination.index, 0, removed)
        }
    
        return dispatch(modifyTour(tour, newTourItems))
      
    },
    handleDropInTaskList = (tasksList, selectedTasks, index) => {
      let newTasksList = [...tasksList.items]
    
    
      selectedTasks.forEach((task) => {
        let taskIndex = newTasksList.findIndex((item) => item['@id'] === task['@id'])
        // if the task was already in the tasklist, remove from its original place 
        if ( taskIndex > -1) {
          newTasksList.splice(taskIndex, 1)
        }
      })

      newTasksList.splice(index, 0, ...selectedTasks)
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
        return position
      }
    }

    // dropped nowhere
    if (!result.destination) {
      return;
    }

    const source = result.source;
    const destination = result.destination;

    // reordered inside the unassigned list or unassigned tours list, do nothing
    if (
      source.droppableId === destination.droppableId &&
      ( source.droppableId === 'unassigned' || source.droppableId === 'unassigned_tours' )
    ) {
      return;
    }

    // did not move anywhere - can bail early
    if (
      source.droppableId === destination.droppableId &&
      source.index === destination.index
    ) {
      return;
    }

    // cannot unassign by drag'n'drop atm
    if (source.droppableId.startsWith('assigned:') && destination.droppableId === 'unassigned') {
      return
    }

    // cannot unassign from tour by drag'n'drop atm
    if (source.droppableId.startsWith('tour:') && destination.droppableId === 'unassigned') {
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
      tour = tourSelectors.selectById(getState(), tourId)
      selectedTasks = tour.itemIds.map(taskId => taskSelectors.selectById(getState(), taskId))
    }
    
    // we want to move linked tasks together only when assigning and adding to a tour
    // so we can keep fine grained control for reordering at will
    if (source.droppableId !== destination.droppableId) {
      selectedTasks =  withLinkedTasks(selectedTasks, allTasks, true)
    }

    if (selectedTasks.length === 0) return // can happen, for example dropping empty tour
    
    if (destination.droppableId.startsWith('tour:')) {
      const tours = selectAllTours(getState())
      var tourId = destination.droppableId.replace('tour:', '')
      const tour = tours.find(t => t['@id'] == tourId)

      if (isTourAssigned(tour)) {
        const tasksLists = selectTaskLists(getState())
        const username = tourIsAssignedTo(tour)
        const tasksList = _.find(tasksLists, tl => tl.username === username)
        const nestedTaskList = makeSelectTaskListItemsByUsername()(getState(), {username})
        const index = getPositionInFlatTaskList(nestedTaskList, destination.index, tourId)
        handleDropInTaskList(tasksList, selectedTasks, index).then(() => {
          handleDropInTour(tour, selectedTasks, source, destination)
        })
      } else {
        handleDropInTour(tour, selectedTasks, source, destination)
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