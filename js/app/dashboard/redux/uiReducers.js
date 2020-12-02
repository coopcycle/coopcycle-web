import {
  MODIFY_TASK_LIST_REQUEST,
  MODIFY_TASK_LIST_REQUEST_SUCCESS
} from "./actions";

const initialState = {
  taskListsLoading: false,
}

export default (state = initialState, action) => {
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
    default:
      return state
  }
}
