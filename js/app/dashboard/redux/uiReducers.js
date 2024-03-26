import _ from "lodash";
import {
  MODIFY_TASK_LIST_REQUEST,
  MODIFY_TASK_LIST_REQUEST_SUCCESS,
  OPEN_NEW_TASK_MODAL,
  SET_CURRENT_TASK,
  TOGGLE_TOUR_PANEL_EXPANDED,
  TOGGLE_TOUR_LOADING,
  APPEND_TO_UNASSIGNED_TASKS,
  INSERT_IN_UNASSIGNED_TASKS,
  APPEND_TO_UNASSIGNED_TOURS,
  INSERT_IN_UNASSIGNED_TOURS
} from "./actions";

// will be overrided by js/shared/src/logistics/redux/uiReducers.js when we reduce reducers so set initialState there
const initialState = {}

export default (state = initialState, action) => {
  let unassignedTasksIdsOrder
  let unassignedToursOrGroupsOrderIds

  switch (action.type) {
    case MODIFY_TASK_LIST_REQUEST:
      return {
        ...state,
        taskListsLoading: true,
      }

    case MODIFY_TASK_LIST_REQUEST_SUCCESS:
      return {
        ...state,
        taskListsLoading: false,
      }

    case OPEN_NEW_TASK_MODAL:
      return {
        ...state,
        currentTask: null,
      }

    case SET_CURRENT_TASK:
      return {
        ...state,
        currentTask: action.task,
      }
    case TOGGLE_TOUR_PANEL_EXPANDED:
      return {
        ...state,
        expandedTourPanelsIds: _.xor([...state.expandedTourPanelsIds], [action.tourId])
      }
    case TOGGLE_TOUR_LOADING:
      return {
        ...state,
        loadingTourPanelsIds: _.xor([...state.loadingTourPanelsIds], [action.tourId])
      }
    case APPEND_TO_UNASSIGNED_TASKS:
      unassignedTasksIdsOrder = [...state.unassignedTasksIdsOrder]
      _.remove(unassignedTasksIdsOrder, t => action.taskToRemoveIds.includes(t))
      unassignedTasksIdsOrder = [...unassignedTasksIdsOrder, ...action.tasksToAppendIds]
      return {
        ...state,
        unassignedTasksIdsOrder: unassignedTasksIdsOrder,
      }

    case INSERT_IN_UNASSIGNED_TASKS:
      const tasksToInsertIds = action.tasksToInsert.map(t => t['@id'])
      unassignedTasksIdsOrder = [...state.unassignedTasksIdsOrder]
      _.remove(unassignedTasksIdsOrder, t => tasksToInsertIds.includes(t))
      Array.prototype.splice.apply(unassignedTasksIdsOrder, Array.prototype.concat([ action.index, 0 ], tasksToInsertIds))

      return {
        ...state,
        unassignedTasksIdsOrder: unassignedTasksIdsOrder,
      }

  case APPEND_TO_UNASSIGNED_TOURS:
    unassignedToursOrGroupsOrderIds = [...state.unassignedToursOrGroupsOrderIds]
    _.remove(unassignedToursOrGroupsOrderIds, t => action.itemsToRemoveIds.includes(t))
    unassignedToursOrGroupsOrderIds = [...unassignedToursOrGroupsOrderIds, ...action.itemsToAppendIds]
    return {
      ...state,
      unassignedToursOrGroupsOrderIds: unassignedToursOrGroupsOrderIds,
    }

  case INSERT_IN_UNASSIGNED_TOURS:
    const tourOrGroupToInsert = action.itemId
    unassignedToursOrGroupsOrderIds = [...state.unassignedToursOrGroupsOrderIds]
    _.remove(unassignedToursOrGroupsOrderIds, t => t === tourOrGroupToInsert)
    Array.prototype.splice.apply(unassignedToursOrGroupsOrderIds, Array.prototype.concat([ action.index, 0 ], tourOrGroupToInsert))

    return {
      ...state,
      unassignedToursOrGroupsOrderIds: unassignedToursOrGroupsOrderIds,
    }
  }

  return state
}
