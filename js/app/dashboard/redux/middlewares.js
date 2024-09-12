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
} from './actions'
import _ from 'lodash'
import Centrifuge from 'centrifuge'
import { selectSelectedDate } from '../../coopcycle-frontend-js/logistics/redux'
import { selectLastOptimResult } from './selectors'

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
        case 'task:updated':
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
        case 'v2:task_list:updated':
          const currentDate = selectSelectedDate(getState())
          if (event.data.task_list.date === currentDate.format('YYYY-MM-DD')) {
            dispatch(taskListsUpdated(event.data.task_list))
          } else {
            console.debug('Discarding tasklist event for other day ' + event.data.task_list.date)
          }

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