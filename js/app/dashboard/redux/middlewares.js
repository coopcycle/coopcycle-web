import {
  addCreatedTask,
  setGeolocation,
  updateTask,
  setOffline
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
      transportOptions: {
        polling: {
          extraHeaders: {
            Authorization: `Bearer ${getState().jwt}`
          }
        }
      }
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
