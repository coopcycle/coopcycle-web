import {
  MODIFY_TASK_LIST_REQUEST,
  MODIFY_TASK_LIST_REQUEST_SUCCESS,
  OPEN_NEW_TASK_MODAL,
  SET_CURRENT_TASK,
  CLEAR_PRE_EXPANDED,
} from "./actions";

import {
  CREATE_TASK_LIST_SUCCESS,
} from '../../coopcycle-frontend-js/logistics/redux';


const initialState = {
  taskListsLoading: false,
  currentTask: null,
  preExpanded: [],
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

    case CREATE_TASK_LIST_SUCCESS:
      return {
        ...state,
        preExpanded: [action.payload.username],
      }

    case CLEAR_PRE_EXPANDED:
      return {
        ...state,
        preExpanded: [],
      }
  }

  return state
}
