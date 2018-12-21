import { combineReducers, createStore, applyMiddleware, compose } from 'redux'
import thunk from 'redux-thunk'
import {
  allTasks,
  unassignedTasks,
  taskLists,
  taskListsLoading,
  addModalIsOpen,
  polylineEnabled,
  taskListGroupMode,
  taskFinishedFilter,
  taskCancelledFilter,
  tagsFilter,
  selectedTasks,
  jwt,
  positions,
  offline,
  isDragging
} from './reducers'
import { socketIO } from './middlewares'

const middlewares = [ thunk, socketIO ]

// we maye want enhancing redux dev tools only  in dev ?
// also if server side render is made later, it is
// better to add a guard here
const composeEnhancers = (typeof window !== 'undefined' &&
  window.__REDUX_DEVTOOLS_EXTENSION_COMPOSE__) || compose

let store = createStore(
  combineReducers({
    allTasks,
    unassignedTasks,
    taskLists,
    taskListsLoading,
    addModalIsOpen,
    polylineEnabled,
    taskListGroupMode,
    taskFinishedFilter,
    taskCancelledFilter,
    tagsFilter,
    selectedTasks,
    jwt,
    positions,
    offline,
    isDragging
  }),
  {
    jwt: window.AppData.Dashboard.jwt
  },
  composeEnhancers(applyMiddleware(...middlewares))
)

export default store
