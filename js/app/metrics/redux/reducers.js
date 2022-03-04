import {
  CHANGE_DATE_RANGE,
  CHANGE_VIEW,
} from './actions'

export const initialState = {
  view: 'marketplace',
  dateRange: '30d',
  zeroWaste: false,
  uiTasksMetricsEnabled: false,
}

export default (state = initialState, action = {}) => {

  switch (action.type) {
  case CHANGE_DATE_RANGE:

    return {
      ...state,
      dateRange: action.payload,
    }

  case CHANGE_VIEW:

    return {
      ...state,
      view: action.payload,
    }
  }

  return state
}
