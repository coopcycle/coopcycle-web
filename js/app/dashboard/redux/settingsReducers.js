import _ from 'lodash'

import {
  SET_FILTER_VALUE,
  RESET_FILTERS,
  SET_CLUSTERS_ENABLED,
  SET_POLYLINE_STYLE,
} from './actions'

const defaultFilters = {
  showFinishedTasks: true,
  showCancelledTasks: false,
  alwayShowUnassignedTasks: true,
  tags: [],
  hiddenCouriers: [],
  timeRange: [0, 24],
}

const initialState = {
  filters: defaultFilters,
  isDefaultFilters: true,
  clustersEnabled: false,
  polylineStyle: 'normal',
}

export default (state = initialState, action) => {
  switch (action.type) {

  case SET_FILTER_VALUE:

    const newFilters = {
      ...state.filters,
      [action.key]: action.value
    }

    return {
      ...state,
      filters: newFilters,
      isDefaultFilters: _.isEqual(newFilters, defaultFilters)
    }

  case RESET_FILTERS:

    return {
      ...state,
      filters: defaultFilters,
      isDefaultFilters: true
    }

  case SET_CLUSTERS_ENABLED:

    return {
      ...state,
      clustersEnabled: action.enabled
    }

  case SET_POLYLINE_STYLE:

    return {
      ...state,
      polylineStyle: action.style
    }
  }

  let isDefaultFilters = initialState.isDefaultFilters
  if (Object.prototype.hasOwnProperty.call(state, 'filters') && !Object.prototype.hasOwnProperty.call(state, 'isDefaultFilters')) {
    isDefaultFilters = _.isEqual(state.filters, defaultFilters)
  }

  return {
    ...state,
    filters: Object.prototype.hasOwnProperty.call(state, 'filters') ? state.filters : initialState.filters,
    isDefaultFilters: Object.prototype.hasOwnProperty.call(state, 'isDefaultFilters') ? state.isDefaultFilters : isDefaultFilters,
  }
}
