import {createAction} from 'redux-actions';

export const CREATE_TASK_LIST_REQUEST = 'CREATE_TASK_LIST_REQUEST'
export const CREATE_TASK_LIST_SUCCESS = 'CREATE_TASK_LIST_SUCCESS'
export const CREATE_TASK_LIST_FAILURE = 'CREATE_TASK_LIST_FAILURE'

export const ENABLE_DROP_IN_TOURS = 'ENABLE_DROP_IN_TOURS'
export const DISABLE_DROP_IN_TOURS = 'DISABLE_DROP_IN_TOURS'

export const createTaskListRequest = createAction(CREATE_TASK_LIST_REQUEST)
export const createTaskListSuccess = createAction(CREATE_TASK_LIST_SUCCESS)
export const createTaskListFailure = createAction(CREATE_TASK_LIST_FAILURE)

// actions to enable/disable drop in tours, i.e. when starting to drag an element enable/disable the possibility to drop it in tours
export const enableDropInTours = createAction(ENABLE_DROP_IN_TOURS)
export const disableDropInTours = createAction(DISABLE_DROP_IN_TOURS)