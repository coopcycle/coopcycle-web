import {
  CREATE_TASK_LIST_FAILURE,
  CREATE_TASK_LIST_REQUEST,
  CREATE_TASK_LIST_SUCCESS,
  ENABLE_UNASSIGNED_TOURS_DROPPABLE,
  DISABLE_UNASSIGNED_TOURS_DROPPABLE,
  ENABLE_UNASSIGNED_TOUR_TASKS_DROPPABLE,
  DISABLE_UNASSIGNED_TOUR_TASKS_DROPPABLE,
} from './actions';

const initialState = {
  taskListsLoading: false,
  unassignedToursDroppableDisabled: true,
  unassignedTourTasksDroppableDisabled: false,
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
    
    case ENABLE_UNASSIGNED_TOURS_DROPPABLE:
      return {
        ...state,
        unassignedToursDroppableDisabled: false,
      }
    
    case DISABLE_UNASSIGNED_TOURS_DROPPABLE:
      return {
        ...state,
        unassignedToursDroppableDisabled: true,
      }

    case ENABLE_UNASSIGNED_TOUR_TASKS_DROPPABLE:
      return {
        ...state,
        unassignedTourTasksDroppableDisabled: false,
      }
    
    case DISABLE_UNASSIGNED_TOUR_TASKS_DROPPABLE:
      return {
        ...state,
        unassignedTourTasksDroppableDisabled: true,
      }
  

    default:
      return state
  }
}
