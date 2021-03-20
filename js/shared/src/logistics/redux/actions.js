import {createAction} from 'redux-actions';

export const CREATE_TASK_LIST_REQUEST = 'CREATE_TASK_LIST_REQUEST'
export const CREATE_TASK_LIST_SUCCESS = 'CREATE_TASK_LIST_SUCCESS'
export const CREATE_TASK_LIST_FAILURE = 'CREATE_TASK_LIST_FAILURE'

export const createTaskListRequest = createAction(CREATE_TASK_LIST_REQUEST)
export const createTaskListSuccess = createAction(CREATE_TASK_LIST_SUCCESS)
export const createTaskListFailure = createAction(CREATE_TASK_LIST_FAILURE)
