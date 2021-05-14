import {
  CREATE_TASK_LIST_FAILURE,
  CREATE_TASK_LIST_REQUEST,
  CREATE_TASK_LIST_SUCCESS
} from './actions';

const initialState = {
  taskListsLoading: false,
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
    default:
      return state
  }
}
