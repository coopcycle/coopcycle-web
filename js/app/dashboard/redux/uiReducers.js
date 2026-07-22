import _ from "lodash";
import {
  MODIFY_TASK_LIST_REQUEST,
  MODIFY_TASK_LIST_REQUEST_SUCCESS,
  MODIFY_TASK_LIST_REQUEST_FAILURE,
  MODIFY_TASK_LIST_REQUEST_DISCARDED,
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
  setTaskToShow,
  loadVehiclesSuccess,
  loadTrailersSuccess,
  setTaskListsLoading,
  setLoadingTaskIds,
  loadWarehousesSuccess,
  setOptimResult,
  startOptimRequest
} from "./actions";

// will be overrided by js/shared/src/logistics/redux/uiReducers.js when we reduce reducers so set initialState there
const initialState = {}

const emptyRequests = { latestRequestId: null, pending: 0 }

/**
 * Keep track, per rider, of the last task list modification we initiated and of how
 * many of them are still in flight. Two PUTs on the same task list can complete out
 * of order, and the API also pushes `v2:task_list:updated` events while we are
 * mutating: without this we would apply an older state on top of a newer one, and the
 * dispatcher would see the list silently reorganize itself.
 * @param {Object} state - Current ui state
 * @param {string} username - Username of the rider
 * @param {function} update - Receives the current bookkeeping, returns the changes
 */
const trackTaskListRequest = (state, username, update) => {
  if (!username) {
    return state
  }

  const taskListsRequests = state.taskListsRequests ?? {}
  const current = taskListsRequests[username] ?? emptyRequests
  const next = { ...taskListsRequests, [username]: { ...current, ...update(current) } }

  return {
    ...state,
    taskListsRequests: next,
    // drag'n'drop is disabled while a task list is being modified, so this must stay
    // true as long as *any* modification is still in flight
    taskListsLoading: Object.values(next).some(({ pending }) => pending > 0),
  }
}

// A purely local optimistic change (no API call) still supersedes an older in-flight
// one, but only the modifications that actually hit the API are counted as pending.
const requestStarted = (requestId, isApiRequest) => current => ({
  latestRequestId: requestId ?? current.latestRequestId,
  pending: isApiRequest ? current.pending + 1 : current.pending,
})

const requestSettled = requestId => current => ({
  pending: requestId ? Math.max(0, current.pending - 1) : current.pending,
})

export default (state = initialState, action) => {
  switch (action.type) {
    case setUnassignedTasksLoading.type:
      return {
        ...state,
        unassignedTasksLoading: action.payload
      }
    case MODIFY_TASK_LIST_REQUEST:
      return trackTaskListRequest(
        state,
        action.username,
        requestStarted(action.requestId, action.isApiRequest)
      )

    case MODIFY_TASK_LIST_REQUEST_SUCCESS:
    case MODIFY_TASK_LIST_REQUEST_FAILURE:
    case MODIFY_TASK_LIST_REQUEST_DISCARDED:
      return trackTaskListRequest(
        state,
        action.username,
        requestSettled(action.requestId)
      )

    case setTaskListsLoading.type:
      return {
        ...state,
        taskListsLoading: action.payload,
      }

    case setLoadingTaskIds.type:
      return {
        ...state,
        loadingTaskIds: action.payload,
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
      unassignedToursOrGroupsOrderIds = _.uniq([...unassignedToursOrGroupsOrderIds, ...action.payload.itemsToAppendIds])
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
    case loadVehiclesSuccess.type:
      return {
        ...state,
        vehiclesLoading: false,
      }
    case loadTrailersSuccess.type:
      return {
        ...state,
        trailersLoading: false,
      }
    case loadWarehousesSuccess.type:
      return {
        ...state,
        warehousesLoading: false
      }
    case startOptimRequest.type:
      return {
        ...state,
        optimLoading: true,
      }
    case setOptimResult.type:
      return {
        ...state,
        optimLoading: false,
      }
  }

  return state
}
