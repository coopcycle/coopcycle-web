import _ from "lodash";
import {
  MODIFY_TASK_LIST_REQUEST,
  MODIFY_TASK_LIST_REQUEST_SUCCESS,
  OPEN_NEW_TASK_MODAL,
  SET_CURRENT_TASK,
  TOGGLE_TOUR_PANEL_EXPANDED,
  TOGGLE_TOUR_LOADING,
  setUnassignedTasksLoading,
  appendToUnassignedTasks,
  insertInUnassignedTasks,
  appendToUnassignedTours,
  insertInUnassignedTours
} from "./actions";

// will be overrided by js/shared/src/logistics/redux/uiReducers.js when we reduce reducers so set initialState there
const initialState = {}

export default (state = initialState, action) => {
  switch (action.type) {
    case setUnassignedTasksLoading.type:
      return {
        ...state,
        unassignedTasksLoading: action.payload
      }
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
    case appendToUnassignedTasks.type: {
      let unassignedTasksIdsOrder
      unassignedTasksIdsOrder = [...state.unassignedTasksIdsOrder]
      _.remove(unassignedTasksIdsOrder, t => action.payload.taskToRemoveIds.includes(t))
      unassignedTasksIdsOrder = [...unassignedTasksIdsOrder, ...action.payload.tasksToAppendIds]
      return {
        ...state,
        unassignedTasksIdsOrder: unassignedTasksIdsOrder,
      }}

    case insertInUnassignedTasks.type: {
      let unassignedTasksIdsOrder
      const tasksToInsertIds = action.payload.tasksToInsert.map(t => t['@id'])
      unassignedTasksIdsOrder = [...state.unassignedTasksIdsOrder]
      _.remove(unassignedTasksIdsOrder, t => tasksToInsertIds.includes(t))
      Array.prototype.splice.apply(unassignedTasksIdsOrder, Array.prototype.concat([ action.payload.index, 0 ], tasksToInsertIds))

      return {
        ...state,
        unassignedTasksIdsOrder: unassignedTasksIdsOrder,
      }}

  case appendToUnassignedTours.type: {
    let unassignedToursOrGroupsOrderIds
    unassignedToursOrGroupsOrderIds = [...state.unassignedToursOrGroupsOrderIds]
    _.remove(unassignedToursOrGroupsOrderIds, t => action.payload.itemsToRemoveIds.includes(t))
    unassignedToursOrGroupsOrderIds = [...unassignedToursOrGroupsOrderIds, ...action.payload.itemsToAppendIds]
    return {
      ...state,
      unassignedToursOrGroupsOrderIds: unassignedToursOrGroupsOrderIds,
    }}
  case insertInUnassignedTours.type: {
    let unassignedToursOrGroupsOrderIds
    const tourOrGroupToInsert = action.payload.itemId
    unassignedToursOrGroupsOrderIds = [...state.unassignedToursOrGroupsOrderIds]
    _.remove(unassignedToursOrGroupsOrderIds, t => t === tourOrGroupToInsert)
    Array.prototype.splice.apply(unassignedToursOrGroupsOrderIds, Array.prototype.concat([ action.payload.index, 0 ], tourOrGroupToInsert))

    return {
      ...state,
      unassignedToursOrGroupsOrderIds: unassignedToursOrGroupsOrderIds,
    }}
  }

  return state
}
