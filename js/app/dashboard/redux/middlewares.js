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
  setGeneralSettings,
  MODIFY_TASK_LIST_REQUEST,
  setOptimResult,
  setMapFilterValue,
  updateTour,
  precogUpdated,
  precogGone,
  precogSynced,
  precogExpired,
} from './actions'
import _ from 'lodash'
import { createCentrifuge, subscribe } from '../../centrifugo/client'
import { selectSelectedDate } from '../../coopcycle-frontend-js/logistics/redux'
import { selectLastOptimResult } from './selectors'

// Check every 30s
const OFFLINE_TIMEOUT_INTERVAL = (30 * 1000)

// Redis drops an expired ghost on its own, but Centrifugo has no way to say so:
// an expiry is the absence of a heartbeat, not an event. Every ghost therefore
// carries the moment it stops being true, and the board enforces it locally.
const PRECOG_SWEEP_INTERVAL = (5 * 1000)

let centrifuge

function sweepPrecog(dispatch, getState) {
  const now = Date.now()
  const expired = Object.values(getState().precog)
    .filter(ghost => Date.parse(ghost.expiresAt) <= now)
    .map(ghost => ghost.id)

  if (expired.length > 0) {
    dispatch(precogExpired(expired))
  }

  setTimeout(() => sweepPrecog(dispatch, getState), PRECOG_SWEEP_INTERVAL)
}

/**
 * Pulls the whole set of in-flight carts.
 *
 * Called on every connect, reconnects included: while the socket was down the
 * board missed both arrivals and departures, and no amount of replaying events
 * afterwards can tell it which. Replacing the set is the only honest answer.
 */
function syncPrecog(dispatch) {
  fetch('/admin/precog', { headers: { 'Accept': 'application/json' } })
    .then(response => response.ok ? response.json() : Promise.reject(response.status))
    .then(({ precog }) => dispatch(precogSynced(precog)))
    .catch(() => { /* the board keeps what it has until the next connect */ })
}

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

  // No token means no realtime: the page was rendered without one, or we are in
  // a test. Attempting to connect would subscribe to undefined channels, which
  // the SDK rejects outright.
  if (!centrifuge && getState().config.centrifugoToken) {

    centrifuge = createCentrifuge(getState().config.centrifugoToken, {
      onConnected: () => {
        if (getState().config.precogEnabled) {
          syncPrecog(dispatch)
        }
      },
    })

    subscribe(centrifuge, getState().config.centrifugoEventsChannel, function(data) {
      const { event } = data
      const currentDate = selectSelectedDate(getState())

      console.debug('Received event : ' + event.name)

      switch (event.name) {
        case 'task:started':
        case 'task:done':
        case 'task:failed':
        case 'task:cancelled':
        case 'task:created':
        case 'task:rescheduled':
        case 'task:incident-reported':
        case 'task:updated':
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
        case 'tour:created':
        case 'tour:updated':
            if (event.data.tour.date === currentDate.format('YYYY-MM-DD')) {
              dispatch(updateTour(event.data.tour))
            }
            break
        case 'precog:updated':
          dispatch(precogUpdated(event.data.precog))
          break
        case 'precog:gone':
          dispatch(precogGone(event.data.precog))
          break
        case 'v2:task_list:updated':
          if (event.data.task_list.date === currentDate.format('YYYY-MM-DD')) {
            dispatch(taskListsUpdated(event.data.task_list))
          } else {
            console.debug('Discarding tasklist event for other day ' + event.data.task_list.date)
          }
          break
      }
    })

    subscribe(centrifuge, getState().config.centrifugoTrackingChannel, function(data) {
      pulse()
      dispatch(setGeolocation(data.user, data.coords, data.ts))
    }, { needsToken: true })

    centrifuge.connect()

    setTimeout(() => {
      checkLastSeen(dispatch)
    }, OFFLINE_TIMEOUT_INTERVAL)

    if (getState().config.precogEnabled) {
      setTimeout(() => sweepPrecog(dispatch, getState), PRECOG_SWEEP_INTERVAL)
    }

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

  if (action.type === setMapFilterValue.type) {
    state = getState()
    window.localStorage.setItem("cpccl__dshbd__map__fltrs", JSON.stringify(state.settings.mapFilters))
  }

  if (action.type === RESET_FILTERS) {
    state = getState()
    window.localStorage.removeItem("cpccl__dshbd__fltrs")
  }

  if (action.type === setGeneralSettings.type || action.type === SHOW_RECURRENCE_RULES || action.type === SET_TOURS_ENABLED) {
    state = getState()
    let generalSettings = {...state.settings}
    delete generalSettings.filters
    window.localStorage.setItem(`cpccl__dshbd__settings`, JSON.stringify(generalSettings))
  }

  return result
}

export const resetOptimizationResult = ({ getState, dispatch }) => (next) => (action) => {

  if (selectLastOptimResult(getState()) && action.type === MODIFY_TASK_LIST_REQUEST) {
    dispatch(setOptimResult())
  }

  return next(action)
}