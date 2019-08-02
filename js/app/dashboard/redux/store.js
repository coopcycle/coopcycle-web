import { combineReducers, createStore, applyMiddleware, compose } from 'redux'
import thunk from 'redux-thunk'
import reducer from './reducers'
import { socketIO } from './middlewares'
import moment from 'moment'

const middlewares = [ thunk, socketIO ]

// we maye want enhancing redux dev tools only  in dev ?
// also if server side render is made later, it is
// better to add a guard here
const composeEnhancers = (typeof window !== 'undefined' &&
  window.__REDUX_DEVTOOLS_EXTENSION_COMPOSE__) || compose

let store = createStore(
  reducer,
  {
    date: moment(window.AppData.Dashboard.date),
    jwt: window.AppData.Dashboard.jwt,
    unassignedTasks: _.filter(window.AppData.Dashboard.tasks, task => !task.isAssigned),
    allTasks: window.AppData.Dashboard.tasks,
    taskLists: window.AppData.Dashboard.taskLists,
    tagsFilter: {
      selectedTagsList: window.AppData.Dashboard.tags,
      showUntaggedTasks: true
    },
    taskUploadFormErrors: window.AppData.Dashboard.taskUploadFormErrors,
    tags: window.AppData.Dashboard.tags,
    nav: window.AppData.Dashboard.nav,
    couriersList: window.AppData.Dashboard.couriersList,
  },
  composeEnhancers(applyMiddleware(...middlewares))
)

export default store
