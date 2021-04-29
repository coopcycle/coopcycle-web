import { createAction } from 'redux-actions'

export const CHANGE_DATE_RANGE = '@metrics/CHANGE_DATE_RANGE'
export const CHANGE_VIEW = '@metrics/CHANGE_VIEW'

export const changeDateRange = createAction(CHANGE_DATE_RANGE)
export const changeView = createAction(CHANGE_VIEW)
