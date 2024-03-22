import _ from 'lodash';
import {
  CREATE_TASK_LIST_FAILURE,
  CREATE_TASK_LIST_REQUEST,
  CREATE_TASK_LIST_SUCCESS,
  SET_IS_TOUR_DRAGGING,
} from './actions';

const initialState = {
  taskListsLoading: false,
  isTourDragging: true,
  currentTask: null,
  expandedTourPanelIds: [],
  loadingTourPanelsIds: [],
  unassignedTasksIdsOrder: []
}

export default (state = initialState, action) => {
  let unassignedTasksIdsOrder
  let unassignedToursOrGroupsOrderIds

  switch (action.type) {
    case CREATE_TASK_LIST_REQUEST:
      return {
        ...state,
        taskListsLoading: true,
      }

    case CREATE_TASK_LIST_SUCCESS:
    case CREATE_TASK_LIST_FAILURE:
      return {
        ...state,
        taskListsLoading: false,
      }

    case SET_IS_TOUR_DRAGGING:
      return {
        ...state,
        isTourDragging: action.payload,
      }

    case 'APPEND_TO_UNASSIGNED_TASKS':
      unassignedTasksIdsOrder = [...state.unassignedTasksIdsOrder]
      _.remove(unassignedTasksIdsOrder, t => action.taskToRemoveIds.includes(t))
      unassignedTasksIdsOrder = [...unassignedTasksIdsOrder, ...action.tasksToAppendIds]
      return {
        ...state,
        unassignedTasksIdsOrder: unassignedTasksIdsOrder,
      }

    case 'INSERT_IN_UNASSIGNED_TASKS':
      const tasksToInsertIds = action.tasksToInsert.map(t => t['@id'])
      unassignedTasksIdsOrder = [...state.unassignedTasksIdsOrder]
      _.remove(unassignedTasksIdsOrder, t => tasksToInsertIds.includes(t))
      Array.prototype.splice.apply(unassignedTasksIdsOrder, Array.prototype.concat([ action.index, 0 ], tasksToInsertIds))

      return {
        ...state,
        unassignedTasksIdsOrder: unassignedTasksIdsOrder,
      }

  case 'APPEND_TO_UNASSIGNED_TOURS':
    unassignedToursOrGroupsOrderIds = [...state.unassignedToursOrGroupsOrderIds]
    _.remove(unassignedToursOrGroupsOrderIds, t => action.itemsToRemoveIds.includes(t))
    unassignedToursOrGroupsOrderIds = [...unassignedToursOrGroupsOrderIds, ...action.itemsToAppendIds]
    return {
      ...state,
      unassignedToursOrGroupsOrderIds: unassignedToursOrGroupsOrderIds,
    }

  case 'INSERT_IN_UNASSIGNED_TOURS':
    const tourOrGroupToInsert = action.itemId
    unassignedToursOrGroupsOrderIds = [...state.unassignedToursOrGroupsOrderIds]
    _.remove(unassignedToursOrGroupsOrderIds, t => t === tourOrGroupToInsert)
    Array.prototype.splice.apply(unassignedToursOrGroupsOrderIds, Array.prototype.concat([ action.index, 0 ], tourOrGroupToInsert))

    return {
      ...state,
      unassignedToursOrGroupsOrderIds: unassignedToursOrGroupsOrderIds,
    }

    default:
      return state
  }
}
