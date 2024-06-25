import {
  CREATE_TASK_LIST_FAILURE,
  CREATE_TASK_LIST_REQUEST,
  CREATE_TASK_LIST_SUCCESS,
  SET_IS_TOUR_DRAGGING,
} from './actions';

const initialState = {
  organizationsLoading: true,
  taskListsLoading: false,
  isTourDragging: true,
  currentTask: null,
  expandedTourPanelIds: [],
  loadingTourPanelsIds: [],
  unassignedTasksIdsOrder: []
}

export default (state = initialState, action) => {
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

    default:
      return state
  }
}
