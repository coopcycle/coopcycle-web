import _ from 'lodash'

import {
  OPEN_ADD_USER,
  CLOSE_ADD_USER,
  TOGGLE_POLYLINE,
  TOGGLE_TASK,
  SELECT_TASK,
  SELECT_TASKS,
  SET_TASK_LIST_GROUP_MODE,
  OPEN_NEW_TASK_MODAL,
  CLOSE_NEW_TASK_MODAL,
  SET_CURRENT_TASK,
  CREATE_TASK_REQUEST,
  CREATE_TASK_SUCCESS,
  CREATE_TASK_FAILURE,
  COMPLETE_TASK_FAILURE,
  CANCEL_TASK_FAILURE,
  OPEN_FILTERS_MODAL,
  CLOSE_FILTERS_MODAL,
  OPEN_SETTINGS,
  CLOSE_SETTINGS,
  LOAD_TASK_EVENTS_REQUEST,
  LOAD_TASK_EVENTS_SUCCESS,
  LOAD_TASK_EVENTS_FAILURE,
  ADD_IMPORT,
  IMPORT_SUCCESS,
  IMPORT_ERROR,
  OPEN_IMPORT_MODAL,
  CLOSE_IMPORT_MODAL,
  CLEAR_SELECTED_TASKS,
  RIGHT_PANEL_MORE_THAN_HALF,
  RIGHT_PANEL_LESS_THAN_HALF,
  OPEN_RECURRENCE_RULE_MODAL,
  CLOSE_RECURRENCE_RULE_MODAL,
  SET_CURRENT_RECURRENCE_RULE,
  UPDATE_RECURRENCE_RULE_SUCCESS,
  UPDATE_RECURRENCE_RULE_REQUEST,
  DELETE_RECURRENCE_RULE_SUCCESS,
  UPDATE_RECURRENCE_RULE_ERROR,
  OPEN_EXPORT_MODAL,
  CLOSE_EXPORT_MODAL,
  OPEN_CREATE_GROUP_MODAL,
  CLOSE_CREATE_GROUP_MODAL,
  OPEN_REPORT_INCIDENT_MODAL,
  CLOSE_REPORT_INCIDENT_MODAL,
  OPEN_ADD_TASK_TO_GROUP_MODAL,
  CLOSE_ADD_TASK_TO_GROUP_MODAL,
  RESTORE_TASK_FAILURE,
  OPEN_CREATE_DELIVERY_MODAL,
  CLOSE_CREATE_DELIVERY_MODAL,
  OPEN_TASK_RESCHEDULE_MODAL,
  CLOSE_TASK_RESCHEDULE_MODAL,
  CREATE_TOUR_REQUEST,
  CREATE_TOUR_REQUEST_SUCCESS,
  CREATE_GROUP_REQUEST,
  CREATE_GROUP_SUCCESS,
  MODIFY_TASK_LIST_REQUEST,
  ADD_TASK_TO_GROUP_REQUEST,
  toggleTourPolyline,
  openCreateTourModal,
  closeCreateTourModal,
  MODIFY_TOUR_REQUEST_SUCCESS,
} from './actions'

import {
  recurrenceRulesAdapter,
} from './selectors'
import { tokenRefreshSuccess } from '../utils/client'

const initialState = {
  addModalIsOpen: false,
  polylineEnabled: {},
  tourPolylinesEnabled: {},
  taskListGroupMode: 'GROUP_MODE_FOLDERS',
  selectedTasks: [],
  jwt: '',
  taskModalIsOpen: false,
  isTaskModalLoading: false,
  completeTaskErrorMessage: null,
  filtersModalIsOpen: false,
  settingsModalIsOpen: false,
  isLoadingTaskEvents: false,
  taskEvents: {},
  imports: {},
  importModalIsOpen: false,
  rightPanelSplitDirection: 'vertical',
  recurrenceRuleModalIsOpen: false,
  currentRecurrenceRule: null,
  rrules: recurrenceRulesAdapter.getInitialState(),
  recurrenceRulesLoading: false,
  recurrenceRulesErrorMessage: '',
  exportModalIsOpen: false,
  createGroupModalIsOpen: false,
  reportIncidentModalIsOpen: false,
  isCreateGroupButtonLoading: false,
  isCreateDeliveryModalVisible: false,
  isCreateTourModalVisible: false,
  isCreateTourButtonLoading: false,
  isTaskRescheduleModalVisible: false,
}

export const addModalIsOpen = (state = false, action) => {
  switch(action.type) {
  case OPEN_ADD_USER:
    return true
  case CLOSE_ADD_USER:
    return false
  default:
    return state
  }
}

export const polylineEnabled = (state = {}, action) => {
  switch (action.type) {
  case TOGGLE_POLYLINE:
    let newState = { ...state }
    const { username } = action
    newState[username] = !state[username]

    return newState
  default:
    return state
  }
}

export const tourPolylinesEnabled = (state = {}, action) => {
  switch (action.type) {
  case toggleTourPolyline.type:
    let newState = { ...state }
    const tourId = action.payload
    newState[tourId] = !state[tourId]
    return newState
  default:
    return state
  }
}

export const selectedTasks = (state = [], action) => {
  /*
    FIXME selectedTasks is an array of task ids, not tasks objects
  */
  let newState

  switch (action.type) {
    case TOGGLE_TASK:

      if (-1 !== state.indexOf(action.taskId)) { // let's remove this task!
        if (!action.multiple) {
          return []
        }
        return _.filter(state, task => task !== action.taskId)
      }

      newState = action.multiple ? state.slice(0) : []
      newState.push(action.taskId)
      return newState
  case SELECT_TASK:
    if (-1 !== state.indexOf(action.taskId)) {
      return state
    }
    return [action.taskId]
  case SELECT_TASKS:
    return action.taskIds
  case CLEAR_SELECTED_TASKS:
  case MODIFY_TASK_LIST_REQUEST:
  case MODIFY_TOUR_REQUEST_SUCCESS:
  case CREATE_TOUR_REQUEST:
  case CREATE_GROUP_REQUEST:
  case ADD_TASK_TO_GROUP_REQUEST:
    // OPTIMIZATION
    // Make sure the array if not already empty
    // before returning a new reference
    if (state.length > 0) {
      return []
    }
  break
  }
  return state
}

export const taskListGroupMode = (state = 'GROUP_MODE_FOLDERS', action) => {
  switch (action.type) {
  case SET_TASK_LIST_GROUP_MODE:
    return action.mode
  default:
    return state
  }
}

export const jwt = (state = '', action) => {
  switch (action.type) {
  case tokenRefreshSuccess.type:

    return action.payload

  default:

    return state
  }
}

export const taskModalIsOpen = (state = false, action) => {
  switch(action.type) {
  case OPEN_NEW_TASK_MODAL:
    return true
  case CLOSE_NEW_TASK_MODAL:
    return false
  case SET_CURRENT_TASK:

    if (!!action.task) {
      return true
    }

    return false
  default:
    return state
  }
}

export const isTaskModalLoading = (state = false, action) => {
  switch(action.type) {
  case CREATE_TASK_REQUEST:
    return true
  case CREATE_TASK_SUCCESS:
  case CREATE_TASK_FAILURE:
  case COMPLETE_TASK_FAILURE:
  case CANCEL_TASK_FAILURE:
  case RESTORE_TASK_FAILURE:
    return false
  default:
    return state
  }
}

export const completeTaskErrorMessage = (state = null, action) => {
  switch(action.type) {
  case CREATE_TASK_REQUEST:
  case CREATE_TASK_SUCCESS:
    return null
  case COMPLETE_TASK_FAILURE:

    const { error } = action

    if (error.response) {
      // The request was made and the server responded with a status code
      // that falls out of the range of 2xx
      if (error.response.status === 400) {
        if (Object.prototype.hasOwnProperty.call(error.response.data, '@type') && error.response.data['@type'] === 'hydra:Error') {
          return error.response.data['hydra:description']
        }
      }
    } else if (error.request) {
      // The request was made but no response was received
      // `error.request` is an instance of XMLHttpRequest in the browser and an instance of
      // http.ClientRequest in node.js
    } else {
      // Something happened in setting up the request that triggered an Error
    }

    break
  }

  return state
}

export const filtersModalIsOpen = (state = initialState.filtersModalIsOpen, action) => {
  switch (action.type) {
  case OPEN_FILTERS_MODAL:
    return true
  case CLOSE_FILTERS_MODAL:
    return false
  default:
    return state
  }
}

export const settingsModalIsOpen = (state = initialState.settingsModalIsOpen, action) => {
  switch (action.type) {
  case OPEN_SETTINGS:

    return true
  case CLOSE_SETTINGS:

    return false
  default:
    return state
  }
}

export const importModalIsOpen = (state = false, action) => {
  switch(action.type) {
  case OPEN_IMPORT_MODAL:
    return true
  case CLOSE_IMPORT_MODAL:
    return false
  default:
    return state
  }
}

export const isLoadingTaskEvents = (state = initialState.isLoadingTaskEvents, action) => {
  switch (action.type) {
  case LOAD_TASK_EVENTS_REQUEST:

    return true
  case LOAD_TASK_EVENTS_SUCCESS:
  case LOAD_TASK_EVENTS_FAILURE:

    return false
  }

  return state
}

export const taskEvents = (state = initialState.taskEvents, action) => {
  switch (action.type) {
  case LOAD_TASK_EVENTS_SUCCESS:
    return {
      ...state,
      [action.task['@id']]: action.events
    }
  }

  return state
}

export const imports = (state = initialState.imports, action) => {
  switch (action.type) {
  case ADD_IMPORT:
    return {
      ...state,
      [ action.token ]: '',
    }
  case IMPORT_SUCCESS:
    return _.omit(state, [ action.token ])
  case IMPORT_ERROR:
    return {
      ...state,
      [ action.token ]: action.message,
    }
  case OPEN_IMPORT_MODAL:
    return {}
  }

  return state
}

export const rightPanelSplitDirection = (state = initialState.rightPanelSplitDirection, action) => {
  switch (action.type) {
  case RIGHT_PANEL_MORE_THAN_HALF:

    return 'horizontal'
  case RIGHT_PANEL_LESS_THAN_HALF:

    return 'vertical'
  }

  return state
}

export const recurrenceRuleModalIsOpen = (state = false, action) => {
  switch(action.type) {
  case OPEN_RECURRENCE_RULE_MODAL:
    return true
  case CLOSE_RECURRENCE_RULE_MODAL:
    return false
  case SET_CURRENT_RECURRENCE_RULE:

    if (!!action.recurrenceRule) {
      return true
    }

    return false
  }

  return state
}

export const currentRecurrenceRule = (state = null, action) => {
  switch(action.type) {
  case SET_CURRENT_RECURRENCE_RULE:

    return action.recurrenceRule
  case CLOSE_RECURRENCE_RULE_MODAL:
    return null
  }

  return state
}

export const rrules = (state = initialState.rrules, action) => {
  switch(action.type) {
  case UPDATE_RECURRENCE_RULE_SUCCESS:

    return recurrenceRulesAdapter.upsertOne(state, action.recurrenceRule)
  case DELETE_RECURRENCE_RULE_SUCCESS:

    return recurrenceRulesAdapter.removeOne(state, action.recurrenceRule)
  }

  return state
}

export const recurrenceRulesLoading = (state = initialState.recurrenceRulesLoading, action) => {
  switch(action.type) {
  case UPDATE_RECURRENCE_RULE_REQUEST:

    return true
  case UPDATE_RECURRENCE_RULE_SUCCESS:
  case DELETE_RECURRENCE_RULE_SUCCESS:
  case UPDATE_RECURRENCE_RULE_ERROR:

    return false
  }

  return state
}

export const recurrenceRulesErrorMessage = (state = initialState.recurrenceRulesErrorMessage, action) => {
  switch(action.type) {
  case UPDATE_RECURRENCE_RULE_REQUEST:
  case UPDATE_RECURRENCE_RULE_SUCCESS:
  case DELETE_RECURRENCE_RULE_SUCCESS:

    return ''
  case UPDATE_RECURRENCE_RULE_ERROR:

    return action.message
  }

  return state
}

export const exportModalIsOpen = (state = false, action) => {
  switch(action.type) {
  case OPEN_EXPORT_MODAL:
    return true
  case CLOSE_EXPORT_MODAL:
    return false
  default:
    return state
  }
}

export const createGroupModalIsOpen = (state = false, action) => {
  switch(action.type) {
  case OPEN_CREATE_GROUP_MODAL:
    return true
  case CLOSE_CREATE_GROUP_MODAL:
    return false
  default:
    return state
  }
}

export const reportIncidentModalIsOpen = (state = false, action) => {
  switch(action.type) {
  case OPEN_REPORT_INCIDENT_MODAL:
    return true
  case CLOSE_REPORT_INCIDENT_MODAL:
    return false
  default:
    return state
  }
}

export const isCreateGroupButtonLoading = (state = initialState.isCreateGroupButtonLoading, action) => {
  switch (action.type) {
  case CREATE_GROUP_REQUEST:
    return true
  case CREATE_GROUP_SUCCESS:
    return false
  default:
    return state
  }
}

export const addTaskToGroupModalIsOpen = (state = false, action) => {
  switch(action.type) {
  case OPEN_ADD_TASK_TO_GROUP_MODAL:
    return true
  case CLOSE_ADD_TASK_TO_GROUP_MODAL:
    return false
  default:
    return state
  }
}

export const isCreateDeliveryModalVisible = (state = initialState.isCreateDeliveryModalVisible, action) => {
  switch (action.type) {
  case OPEN_CREATE_DELIVERY_MODAL:
    return true
  case CLOSE_CREATE_DELIVERY_MODAL:
    return false
  default:
    return state
  }
}

export const isCreateTourModalVisible = (state = initialState.isCreateTourModalVisible, action) => {
  switch (action.type) {
  case openCreateTourModal.type:
    return true
  case closeCreateTourModal.type:
    return false
  default:
    return state
  }
}

export const isCreateTourButtonLoading = (state = initialState.isCreateTourButtonLoading, action) => {
  switch (action.type) {
  case CREATE_TOUR_REQUEST:
    return true
  case CREATE_TOUR_REQUEST_SUCCESS:
    return false
  default:
    return state
  }
}

export const isTaskRescheduleModalVisible = (state = initialState.isTaskRescheduleModalVisible, action) => {
  switch (action.type) {
    case OPEN_TASK_RESCHEDULE_MODAL:
      return true
    case CLOSE_TASK_RESCHEDULE_MODAL:
      return false
    default:
      return state
  }
}
