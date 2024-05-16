import {
  setGeolocation,
  updateTask,
  importSuccess,
  importError,
  taskListsUpdated,
  SET_FILTER_VALUE,
  RESET_FILTERS,
  scanPositions,
  SHOW_RECURRENCE_RULES,
  SET_TOURS_ENABLED,
} from './actions'
import _ from 'lodash'
import Centrifuge from 'centrifuge'

// Check every 30s
const OFFLINE_TIMEOUT_INTERVAL = (30 * 1000)

let centrifuge

function checkLastSeen(dispatch) {
  dispatch(scanPositions())
  setTimeout(() => {
    checkLastSeen(dispatch)
  }, OFFLINE_TIMEOUT_INTERVAL)
}

const pulse = _.debounce(() => {
  const $pulse = $('#pulse')
  if (!$pulse.hasClass('pulse--on')) {
    $pulse.addClass('pulse--on')
  }
  $pulse.addClass('pulse--animate')
  setTimeout(() => $pulse.removeClass('pulse--animate'), 2000)
}, 2000)

export const socketIO = ({ dispatch, getState }) => {
  /*
    Synchronization between mobile dispatch or other web dispatch instances.
  */

  if (!centrifuge) {

    const protocol = window.location.protocol === 'https:' ? 'wss': 'ws'

    centrifuge = new Centrifuge(`${protocol}://${window.location.host}/centrifugo/connection/websocket`)
    centrifuge.setToken(getState().config.centrifugoToken)

    centrifuge.subscribe(getState().config.centrifugoEventsChannel, function(message) {
      const { event } = message.data

      console.debug('Received event : ' + event.name)

      switch (event.name) {
        case 'task:started':
        case 'task:done':
        case 'task:failed':
        case 'task:cancelled':
        case 'task:created':
        case 'task:rescheduled':
        case 'task:incident-reported':
          dispatch(updateTask(event.data.task))
          break
        case 'task:assigned':
        case 'task:unassigned':
          dispatch(updateTask(event.data.task))
          break
        case 'task_import:success':
          dispatch(importSuccess(event.data.token))
          break
        case 'task_import:failure':
          dispatch(importError(event.data.token, event.data.message))
          break
        case 'task_list:updated':
          dispatch(taskListsUpdated(event.data.task_list))
          break
      }
    })

    centrifuge.subscribe(getState().config.centrifugoTrackingChannel, function(message) {
      pulse()
      dispatch(setGeolocation(message.data.user, message.data.coords, message.data.ts))
    })

    centrifuge.connect()

    setTimeout(() => {
      checkLastSeen(dispatch)
    }, OFFLINE_TIMEOUT_INTERVAL)

  }

  return next => action => {

    return next(action)
  }
}

export const persistFilters = ({ getState }) => (next) => (action) => {

  const result = next(action)

  let state
  if (action.type === SET_FILTER_VALUE) {
    state = getState()
    window.localStorage.setItem("cpccl__dshbd__fltrs", JSON.stringify(state.settings.filters))
  }

  if (action.type === RESET_FILTERS) {
    state = getState()
    window.localStorage.removeItem("cpccl__dshbd__fltrs")
  }

  if (action.type === SHOW_RECURRENCE_RULES) {
    state = getState()
    window.sessionStorage.setItem(`recurrence_rules_visible`, JSON.stringify(state.settings.isRecurrenceRulesVisible))
  }

  if (action.type === SET_TOURS_ENABLED) {
    state = getState()
    window.sessionStorage.setItem(`tours_enabled`, JSON.stringify(state.settings.toursEnabled))
  }

  return result
}
