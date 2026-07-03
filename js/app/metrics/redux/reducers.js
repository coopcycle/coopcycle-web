import {
  CHANGE_DATE_RANGE,
  CHANGE_VIEW,
} from './actions'

function getDefaultDateRange() {
  const end = new Date()
  const start = new Date()
  start.setDate(start.getDate() - 30)
  return [
    start.toISOString().split('T')[0],
    end.toISOString().split('T')[0],
  ]
}

export const initialState = {
  view: 'marketplace',
  dateRange: getDefaultDateRange(),
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
