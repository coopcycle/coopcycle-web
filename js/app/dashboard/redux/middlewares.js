import {
  addCreatedTask,
  setGeolocation,
  updateTask,
  setOffline,
  SET_FILTER_VALUE,
  RESET_FILTERS,
} from './actions'
import moment from 'moment'
import _ from 'lodash'

// If the user has not been seen for 5min, it is considered offline
const OFFLINE_TIMEOUT = (5 * 60 * 1000)

// Check every 30s
const OFFLINE_TIMEOUT_INTERVAL = (30 * 1000)

let socket

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

  if (!socket) {

    socket = io(`//${window.location.hostname}`, {
      path: '/tracking/socket.io',
      transports: [ 'websocket' ],
      query: {
        token: getState().jwt,
      },
    })

    socket.on('task:done', data => dispatch(updateTask(data.task)))
    socket.on('task:failed', data => dispatch(updateTask(data.task)))
    socket.on('task:cancelled', data => dispatch(updateTask(data.task)))
    socket.on('task:created', data => dispatch(addCreatedTask(data.task)))

    socket.on('task:assigned', data => dispatch(updateTask(data.task)))
    socket.on('task:unassigned', data => dispatch(updateTask(data.task)))

    socket.on('tracking', data => {
      pulse()
      dispatch(setGeolocation(data.user, data.coords))
    })

    setTimeout(() => {
      checkLastSeen(dispatch, getState)
    }, OFFLINE_TIMEOUT_INTERVAL)

  }

  return next => action => {

    return next(action)
  }
}

function getKey(state) {
  return state.date.format('YYYY-MM-DD')
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
