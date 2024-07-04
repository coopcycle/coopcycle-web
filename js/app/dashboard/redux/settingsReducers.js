import _ from 'lodash'

import {
  SET_FILTER_VALUE,
  RESET_FILTERS,
  SHOW_RECURRENCE_RULES,
  SET_TOURS_ENABLED,
  setGeneralSettings,
} from './actions'

export const defaultFilters = {
  showFinishedTasks: true,
  showCancelledTasks: false,
  showIncidentReportedTasks: true,
  alwayShowUnassignedTasks: false,
  tags: [],
  excludedTags: [],
  excludedOrgs: [],
  includedOrgs: [],
  hiddenCouriers: [],
  timeRange: [0, 24],
  onlyFilter: null,
  unassignedTasksFilters: {
    excludedTags: [],
    includedTags: [],
    excludedOrgs: [],
    includedOrgs: []
  }
}

export const defaultSettings = {
  clustersEnabled: false,
  polylineStyle: 'normal',
  isRecurrenceRulesVisible: true,
  useAvatarColors: false,
  showDistanceAndTime: true,
  showWeightAndVolumeUnit: true,
  toursEnabled: false // is the tour column expanded
}

export const initialState = {
  ...defaultSettings,
  filters: defaultFilters,
  isDefaultFilters: true,
}

export default (state = initialState, action) => {
  switch (action.type) {

  case SHOW_RECURRENCE_RULES:
    return {
      ...state,
      isRecurrenceRulesVisible: action.isChecked
    }

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

  case setGeneralSettings.type:
    return {
      ...state,
      ...action.payload
    }

  case SET_TOURS_ENABLED:

    return {
      ...state,
      toursEnabled: action.enabled
    }
  }

  let isDefaultFilters = initialState.isDefaultFilters

  return {
    ...state,
    filters: Object.prototype.hasOwnProperty.call(state, 'filters') ? state.filters : initialState.filters,
    isDefaultFilters: Object.prototype.hasOwnProperty.call(state, 'isDefaultFilters') ? state.isDefaultFilters : isDefaultFilters,
  }
}
