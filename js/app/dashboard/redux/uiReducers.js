import {
  MODIFY_TASK_LIST_REQUEST,
  MODIFY_TASK_LIST_REQUEST_SUCCESS,
  OPEN_NEW_TASK_MODAL,
  SET_CURRENT_TASK,
} from "./actions";

const initialState = {
  taskListsLoading: false,
  currentTask: null,
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
  }

  return state
}
