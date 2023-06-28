import {createAction} from 'redux-actions';

export const CREATE_TASK_LIST_REQUEST = 'CREATE_TASK_LIST_REQUEST'
export const CREATE_TASK_LIST_SUCCESS = 'CREATE_TASK_LIST_SUCCESS'
export const CREATE_TASK_LIST_FAILURE = 'CREATE_TASK_LIST_FAILURE'

export const ENABLE_UNASSIGNED_TOURS_DROPPABLE = 'ENABLE_UNASSIGNED_TOURS_DROPPABLE'
export const DISABLE_UNASSIGNED_TOURS_DROPPABLE = 'DISABLE_UNASSIGNED_TOURS_DROPPABLE'

export const ENABLE_UNASSIGNED_TOUR_TASKS_DROPPABLE = 'ENABLE_UNASSIGNED_TOUR_TASKS_DROPPABLE'
export const DISABLE_UNASSIGNED_TOUR_TASKS_DROPPABLE = 'DISABLE_UNASSIGNED_TOUR_TASKS_DROPPABLE'

export const createTaskListRequest = createAction(CREATE_TASK_LIST_REQUEST)
export const createTaskListSuccess = createAction(CREATE_TASK_LIST_SUCCESS)
export const createTaskListFailure = createAction(CREATE_TASK_LIST_FAILURE)

export const enableUnassignedToursDroppable = createAction(ENABLE_UNASSIGNED_TOURS_DROPPABLE)
export const disableUnassignedToursDroppable = createAction(DISABLE_UNASSIGNED_TOURS_DROPPABLE)

export const enableUnassignedTourTasksDroppable = createAction(ENABLE_UNASSIGNED_TOUR_TASKS_DROPPABLE)
export const disableUnassignedTourTasksDroppable = createAction(DISABLE_UNASSIGNED_TOUR_TASKS_DROPPABLE)