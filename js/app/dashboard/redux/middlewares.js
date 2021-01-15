import {
  setGeolocation,
  updateTask,
  setOffline,
  importSuccess,
  importError,
  taskListsUpdated,
  SET_FILTER_VALUE,
  RESET_FILTERS,
} from './actions'
import moment from 'moment'
import _ from 'lodash'
import Centrifuge from 'centrifuge'

// If the user has not been seen for 5min, it is considered offline
const OFFLINE_TIMEOUT = (5 * 60 * 1000)

// Check every 30s
const OFFLINE_TIMEOUT_INTERVAL = (30 * 1000)

let centrifuge

function checkLastSeen(dispatch, getState) {

  getState().positions.forEach(position => {
    const diff = moment().diff(position.lastSeen)
    if (diff > OFFLINE_TIMEOUT) {
      dispatch(setOffline(position.username))
    }
  })

  setTimeout(() => {
    checkLastSeen(dispatch, getState)
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

  if (!centrifuge) {

    const protocol = window.location.protocol === 'https:' ? 'wss': 'ws'

    centrifuge = new Centrifuge(`${protocol}://${window.location.hostname}/centrifugo/connection/websocket`)
    centrifuge.setToken(getState().centrifugoToken)

    centrifuge.subscribe(getState().centrifugoEventsChannel, function(message) {
      const { event } = message.data

      switch (event.name) {
        case 'task:started':
        case 'task:done':
        case 'task:failed':
        case 'task:cancelled':
        case 'task:created':
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
        case 'task_collections:updated':
          dispatch(taskListsUpdated(event.data.task_collections))
          break
      }
    })

    centrifuge.subscribe(getState().centrifugoTrackingChannel, function(message) {
      pulse()
      dispatch(setGeolocation(message.data.user, message.data.coords, message.data.ts))
    })

    centrifuge.connect()

    setTimeout(() => {
      checkLastSeen(dispatch, getState)
    }, OFFLINE_TIMEOUT_INTERVAL)

  }

  return next => action => {

    return next(action)
  }
}

function getKey(state) {
  return state.dispatch.date.format('YYYY-MM-DD')
}

export const persistFilters = ({ getState }) => (next) => (action) => {

  const result = next(action)

  let state
  if (action.type === SET_FILTER_VALUE) {
    state = getState()
    window.sessionStorage.setItem(`cpccl__dshbd__fltrs__${getKey(state)}`, JSON.stringify(state.filters))
  }

  if (action.type === RESET_FILTERS) {
    state = getState()
    window.sessionStorage.removeItem(`cpccl__dshbd__fltrs__${getKey(state)}`)
  }

  return result
}
