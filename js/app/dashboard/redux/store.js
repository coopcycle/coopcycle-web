import {createStore, applyMiddleware, compose, combineReducers} from 'redux'
import thunk from 'redux-thunk'
import reduceReducers from 'reduce-reducers';
import { socketIO, persistFilters } from './middlewares'
import {
  dateReducer,
  taskEntityReducers as coreTaskEntityReducers,
  taskListEntityReducers as coreTaskListEntityReducers,
  uiReducers as coreUiReducers,
} from '../../coopcycle-frontend-js/lastmile/redux'
import webReducers from './reducers'
import webTaskEntityReducers from './taskEntityReducers'
import webTaskListEntityReducers from './taskListEntityReducers'
import webUiReducers from './uiReducers'

const middlewares = [ thunk, socketIO, persistFilters ]

// we maye want enhancing redux dev tools only  in dev ?
// also if server side render is made later, it is
// better to add a guard here
const composeEnhancers = (typeof window !== 'undefined' &&
  window.__REDUX_DEVTOOLS_EXTENSION_COMPOSE__) || compose

const reducer = (state, action) => {
  //todo move more properties from webReducers inside `lastmile` state
  let rootState = webReducers(state, action)

  let lastmileState = combineReducers({
    date: dateReducer,
    entities: combineReducers({
      tasks: reduceReducers(coreTaskEntityReducers, webTaskEntityReducers),
      taskLists: reduceReducers(coreTaskListEntityReducers, webTaskListEntityReducers),
    }),
    ui: reduceReducers(coreUiReducers, webUiReducers)
  })(state.lastmile, action)

  return {
    ...rootState,
    lastmile: lastmileState
  }
}

export const createStoreFromPreloadedState = preloadedState => {
  return createStore(
    reducer,
    preloadedState,
    composeEnhancers(
      applyMiddleware(...middlewares)
    )
  )
}
