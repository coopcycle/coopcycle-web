import _ from "lodash";
import {
  MODIFY_TASK_LIST_REQUEST,
  MODIFY_TASK_LIST_REQUEST_SUCCESS,
  OPEN_NEW_TASK_MODAL,
  SET_CURRENT_TASK,
  TOGGLE_TOUR_LOADING,
  setUnassignedTasksLoading,
  appendToUnassignedTasks,
  insertInUnassignedTasks,
  appendToUnassignedTours,
  insertInUnassignedTours,
  SET_TASK_LIST_GROUP_MODE,
  loadOrganizationsSuccess,
  toggleTourPanelExpanded,
  toggleTaskListPanelExpanded,
  toggleTasksGroupPanelExpanded,
  setTaskToShow
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
    case toggleTourPanelExpanded.type:
      return {
        ...state,
        expandedTourPanelsIds: _.xor([...state.expandedTourPanelsIds], [action.payload])
      }
    case toggleTaskListPanelExpanded.type:
      return {
        ...state,
        expandedTaskListPanelsIds: _.xor([...state.expandedTaskListPanelsIds], [action.payload])
      }
    case toggleTasksGroupPanelExpanded.type:
      return {
        ...state,
        expandedTasksGroupPanelIds: _.xor([...state.expandedTasksGroupPanelIds], [action.payload])
      }
    case setTaskToShow.type:
      return {
        ...state,
        taskToShow: action.payload
      }
    case TOGGLE_TOUR_LOADING:
      return {
        ...state,
        loadingTourPanelsIds: _.xor([...state.loadingTourPanelsIds], [action.tourId])
      }
    case appendToUnassignedTasks.type: { // some tasks where added in unassigned, ex: foodtech orders pops, right click unassign
      let unassignedTasksIdsOrder
      unassignedTasksIdsOrder = [...state.unassignedTasksIdsOrder]
      _.remove(unassignedTasksIdsOrder, t => action.payload.taskToRemoveIds.includes(t))
      unassignedTasksIdsOrder = [...unassignedTasksIdsOrder, ...action.payload.tasksToAppendIds]
      return {
        ...state,
        unassignedTasksIdsOrder: unassignedTasksIdsOrder,
      }}

    case insertInUnassignedTasks.type: { // some tasks were inserted at given index, i.e. drag'n drop
      let unassignedTasksIdsOrder
      const tasksToInsertIds = action.payload.tasksToInsert.map(t => t['@id'])
      unassignedTasksIdsOrder = [...state.unassignedTasksIdsOrder]
      _.remove(unassignedTasksIdsOrder, t => tasksToInsertIds.includes(t))
      unassignedTasksIdsOrder.splice(action.payload.index, 0 , ...tasksToInsertIds)

      return {
        ...state,
        unassignedTasksIdsOrder: unassignedTasksIdsOrder,
      }}

    case SET_TASK_LIST_GROUP_MODE: // reset the unassigned tasks order, the ordered tasks will trickle down selectUnassignedTasks -> UnassignedTasks component -> APPEND_TO_UNASSIGNED_TASKS
      return {
        ...state,
        unassignedTasksIdsOrder: [],
      }

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
      unassignedToursOrGroupsOrderIds.splice(action.payload.index, 0, tourOrGroupToInsert)

      return {
        ...state,
        unassignedToursOrGroupsOrderIds: unassignedToursOrGroupsOrderIds,
      }}
    case loadOrganizationsSuccess.type:
      return {
        ...state,
        organizationsLoading: false,
      }
  }

  return state
}
