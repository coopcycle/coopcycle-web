import {
  CREATE_TASK_LIST_FAILURE,
  CREATE_TASK_LIST_REQUEST,
  CREATE_TASK_LIST_SUCCESS,
  ENABLE_DROP_IN_TOURS,
  DISABLE_DROP_IN_TOURS,
} from './actions';

const initialState = {
  taskListsLoading: false,
  areToursDroppable: true,
  currentTask: null,
  expandedTourPanelIds: [],
  loadingTourPanelsIds: []
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

    case ENABLE_DROP_IN_TOURS:
      return {
        ...state,
        areToursDroppable: true,
      }

    case DISABLE_DROP_IN_TOURS:
      return {
        ...state,
        areToursDroppable: false,
      }


    default:
      return state
  }
}
