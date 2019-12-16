import { createStore, applyMiddleware, compose } from 'redux'
import thunk from 'redux-thunk'
import reducer from './reducers'
import { socketIO, persistFilters } from './middlewares'
import moment from 'moment'
import _ from 'lodash'

const middlewares = [ thunk, socketIO, persistFilters ]

// we maye want enhancing redux dev tools only  in dev ?
// also if server side render is made later, it is
// better to add a guard here
const composeEnhancers = (typeof window !== 'undefined' &&
  window.__REDUX_DEVTOOLS_EXTENSION_COMPOSE__) || compose

const date = moment(window.AppData.Dashboard.date)

let preloadedState = {
  date,
  jwt: window.AppData.Dashboard.jwt,
  unassignedTasks: _.filter(window.AppData.Dashboard.tasks, task => !task.isAssigned),
  allTasks: window.AppData.Dashboard.tasks,
  taskLists: window.AppData.Dashboard.taskLists,
  taskUploadFormErrors: window.AppData.Dashboard.taskUploadFormErrors,
  tags: window.AppData.Dashboard.tags,
  nav: window.AppData.Dashboard.nav,
  couriersList: window.AppData.Dashboard.couriersList,
}

const key = date.format('YYYY-MM-DD')
const persistedFilters = window.sessionStorage.getItem(`cpccl__dshbd__fltrs__${key}`)
if (persistedFilters) {
  preloadedState = {
    ...preloadedState,
    filters: JSON.parse(persistedFilters)
  }
}

let store = createStore(
  reducer,
  preloadedState,
  composeEnhancers(applyMiddleware(...middlewares))
)

export default store
