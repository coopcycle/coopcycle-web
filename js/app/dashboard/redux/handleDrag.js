import _ from "lodash"
import { selectItemAssignedTo, selectTaskIdToTourIdMap, selectTaskListByUsername, selectTourById } from "../../../shared/src/logistics/redux/selectors"
import { setIsTourDragging, selectAllTasks } from "../../coopcycle-frontend-js/logistics/redux"
import { clearSelectedTasks,
  insertInUnassignedTasks,
  insertInUnassignedTours,
  putTaskListItems as putTaskListItemsAction,
  modifyTour as modifyTourAction,
  removeTasksFromTour as removeTasksFromTourAction,
  setUnassignedTasksLoading,
  unassignTasks as unassignTasksAction
} from "./actions"
import { belongsToTour, selectGroups, selectOrderOfUnassignedTasks, selectSelectedTasks } from "./selectors"
import { isValidTasksMultiSelect, withOrderTasks } from "./utils"
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
  putTaskListItems=putTaskListItemsAction,
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

  return function(dispatch, getState) {

    /**
     * @param {Object} tasksList - TaskList to be modified
     * @param {Array.Objects} selectedItems - Items to be assigned, list of tasks and tours to be assigned
     * @param {number} index - The index at which we drop
    */
    const handleDropInTaskList = async (tasksList, selectedItems, index) => {
      let newTasksListItems = [...tasksList.items]

      selectedItems.forEach((t) => {
        let itemIndex = newTasksListItems.findIndex((item) => item === t['@id'])
        // if the item was already in the tasklist, remove from its original place
        if (itemIndex > -1) {
          newTasksListItems.splice(itemIndex, 1)
        }

      })

      newTasksListItems.splice(index, 0, ...selectedItems.map(it => it['@id']))

      const previousAssignedTo = selectItemAssignedTo(getState(), selectedItems[0]['@id'])
      if(previousAssignedTo && previousAssignedTo !== tasksList.username) {
        dispatch(setUnassignedTasksLoading(true))
        await dispatch(unassignTasks(previousAssignedTo, selectedItems))
        dispatch(setUnassignedTasksLoading(false))
      }

      return dispatch(putTaskListItems(tasksList.username, newTasksListItems))
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

    const allTasks = selectAllTasks(getState()),
      isTourDrag = result.draggableId.startsWith('tour')

    // FIXME : if a tour or a group is selected, selectSelectedTasks yields [ undefined ] so we test > 1 no > 0
    let selectedTasks = selectSelectedTasks(getState()).length > 1 ? selectSelectedTasks(getState()) : [_.find(allTasks, t => t['@id'] === result.draggableId)]

    // we are moving a whole group, override selectedTasks
    if (result.draggableId.startsWith('group')) {
      let groupId = result.draggableId.split(':')[1]
      selectedTasks = selectGroups(getState()).find(g => g['@id'] == groupId).tasks
    }

    if (selectedTasks.length === 0) return // can happen, for example dropping empty tour

    const taskIdToTourIdMap = selectTaskIdToTourIdMap(getState())

    if(!isTourDrag && !isValidTasksMultiSelect(selectedTasks, taskIdToTourIdMap)){
      toast.warn(i18next.t('ADMIN_DASHBOARD_INVALID_TASKS_SELECTION'), {autoclose: 15000})
      return
    }

    // when we drag n drop we want all tasks of the order/delivery to move alongside
    // except from tour or group, keep them as they are organized
    if (source.droppableId !== destination.droppableId && !result.draggableId.startsWith('tour') && !result.draggableId.startsWith('group')) {
      selectedTasks =  withOrderTasks(selectedTasks, allTasks, taskIdToTourIdMap)
    }

    // sorting tasks to be inserted
    // if the tasks are moved from unassigned -> keep the tasks in the same order in the destination
    // if the tasks are moved from a tour -> keep the tasks in the same order in the destination
    // if the tasks are moved from a task list -> keep the tasks in the same order in the destination
    if (!isTourDrag) {
      if (source.droppableId === 'unassigned') {
        const unassignedTasksOrder = selectOrderOfUnassignedTasks(getState())

        selectedTasks.sort((task1, task2) => {
          const task1Rank = unassignedTasksOrder.findIndex(taskId => taskId === task1['@id'])
          const task2Rank = unassignedTasksOrder.findIndex(taskId => taskId === task2['@id'])
          return  task1Rank - task2Rank
        })
      } else if (source.droppableId.startsWith('tour') || result.draggableId.startsWith('tour')) {
        const tourId = result.draggableId.startsWith('tour') ? result.draggableId.replace('tour:', '') : source.droppableId.replace('tour:', '')
        const tour = selectTourById(getState(), tourId)
        selectedTasks.sort((task1, task2) => {
          const task1Rank = tour.items.findIndex(taskId => taskId === task1['@id'])
          const task2Rank = tour.items.findIndex(taskId => taskId === task2['@id'])
          return  task1Rank - task2Rank
        })
      } else if (source.droppableId.startsWith('assigned')) {
        const username = source.droppableId.replace('assigned:', '')
        const tasksList = selectTaskListByUsername(getState(), {username})
        selectedTasks.sort((task1, task2) => {
          const task1Rank = tasksList.items.findIndex(taskId => taskId === task1['@id'])
          const task2Rank = tasksList.items.findIndex(taskId => taskId === task2['@id'])
          return  task1Rank - task2Rank
        })
      }
    }

    // REORDERING reordered inside the unassigned tours list
    if (
      source.droppableId === destination.droppableId && source.droppableId === 'unassigned_tours'
    ) {
      const itemId = result.draggableId.startsWith('tour:') ? result.draggableId.replace('tour:', '') : result.draggableId.replace('group:', '')
      dispatch(insertInUnassignedTours({itemId: itemId, index: result.destination.index}))
      return;
    }
    // reordered inside the unassigned tasks list
    else if (
      source.droppableId === destination.droppableId && source.droppableId === 'unassigned'
    ) {
      dispatch(insertInUnassignedTasks({tasksToInsert: selectedTasks, index: result.destination.index}))
      return;
    // reordering inside a task list
    } else if (
      source.droppableId === destination.droppableId && source.droppableId.startsWith('assigned')
    ) {
      const username = destination.droppableId.replace('assigned:', '')
      const tasksList = selectTaskListByUsername(getState(), {username})
      const index = destination.index
      let items

      if (isTourDrag) {
        const tourId = result.draggableId.replace('tour:', '')
        items = [selectTourById(getState(), tourId)]
      } else {
        items = selectedTasks
      }

      handleDropInTaskList(tasksList, items, index)
      return;
    // HANDLING TOUR DRAG
    } else if (isTourDrag && destination.droppableId === 'unassigned_tours') { // unassign the tour
      const username = source.droppableId.replace('assigned:', '')
      const tourId = result.draggableId.replace('tour:', '')
      const tour = selectTourById(getState(), tourId)
      dispatch(unassignTasks(username, [tour]))
    } else if (isTourDrag && destination.droppableId.startsWith('assigned:')) {
      const username = destination.droppableId.replace('assigned:', '')
      const tasksList = selectTaskListByUsername(getState(), {username})
      const index = destination.index
      const tourId = result.draggableId.replace('tour:', '')
      const tour = selectTourById(getState(), tourId)
      handleDropInTaskList(tasksList, [tour], index)
    }
    // HANDLING TASK DRAG
    else if (!isTourDrag && destination.droppableId === 'unassigned') {
      if (!belongsToTour(selectedTasks[0])(getState())) {
        dispatch(unassignTasks(selectItemAssignedTo(getState(), selectedTasks[0]['@id']), selectedTasks))
      } else {
        const tourId = selectTaskIdToTourIdMap(getState()).get(selectedTasks[0]['@id'])
        const tour = selectTourById(getState(), tourId)
        dispatch(removeTasksFromTour(tour, selectedTasks))
      }
    } else if (!isTourDrag && destination.droppableId.startsWith('tour:')) {
      var tourId = destination.droppableId.replace('tour:', '')
      const tour = selectTourById(getState(), tourId)

      var newTourItems = [ ...tour.items ]

      // Reorder tasks inside a tour
      if (source.droppableId === destination.droppableId) {
        _.remove(newTourItems, t => selectedTasks.find(selectedTask => selectedTask['@id'] === t))
      }
      // moving single tasks between tours -> remove from source tour
      else if (source.droppableId.startsWith('tour:')) {
        var sourceTourId = source.droppableId.replace('tour:', '')
        const sourceTour = selectTourById(getState(), sourceTourId)
        dispatch(removeTasksFromTour(sourceTour, selectedTasks))
      } // moving from a tasklist "root level" to a tour -> remove from tasklist
      else if (source.droppableId.startsWith('assigned:')) {
        const username = source.droppableId.replace('assigned:', '')
        dispatch(unassignTasks(username, selectedTasks))
      }

      newTourItems.splice(destination.index, 0, ...selectedTasks.map(t => t['@id']))

      dispatch(modifyTour(tour, newTourItems))

    }
    else if (!isTourDrag && destination.droppableId.startsWith('assigned:')) {
      const username = destination.droppableId.replace('assigned:', '')
      const tasksList = selectTaskListByUsername(getState(), {username})
      const index = destination.index

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