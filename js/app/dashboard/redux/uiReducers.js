import _ from "lodash";
import {
  MODIFY_TASK_LIST_REQUEST,
  MODIFY_TASK_LIST_REQUEST_SUCCESS,
  OPEN_NEW_TASK_MODAL,
  SET_CURRENT_TASK,
  TOGGLE_TOUR_PANEL_EXPANDED,
  TOGGLE_TOUR_LOADING
} from "./actions";

// will be overrided by js/shared/src/logistics/redux/uiReducers.js when we reduce reducers so set initialState there
const initialState = {}

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
    case TOGGLE_TOUR_PANEL_EXPANDED:
      return {
        ...state,
        expandedTourPanelsIds: _.xor([...state.expandedTourPanelsIds], [action.tourId])
      }
    case TOGGLE_TOUR_LOADING:
      return {
        ...state,
        loadingTourPanelsIds: _.xor([...state.loadingTourPanelsIds], [action.tourId])
      }
  }

  return state
}
