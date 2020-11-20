import {
  MODIFY_TASK_LIST_REQUEST,
  MODIFY_TASK_LIST_REQUEST_SUCCESS,
  OPEN_MARKER_MODAL,
  CLOSE_MARKER_MODAL,
} from "./actions";

const initialState = {
  taskListsLoading: false,
  markerModalIsOpen: false,
  markerModalTask: null,
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

    case OPEN_MARKER_MODAL:
      return {
        ...state,
        markerModalIsOpen: true,
        markerModalTask: action.task,
      }

    case CLOSE_MARKER_MODAL:
      return {
        ...state,
        markerModalIsOpen: false,
        markerModalTask: null,
      }
    default:
      return state
  }
}
