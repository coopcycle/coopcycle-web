import {createStore, applyMiddleware, compose, combineReducers} from 'redux'
import thunk from 'redux-thunk'
import reduceReducers from 'reduce-reducers';
import { socketIO, persistFilters } from './middlewares'
import {
  dateReducer,
  taskEntityReducers as coreTaskEntityReducers,
  taskListEntityReducers as coreTaskListEntityReducers,
  uiReducers as coreUiReducers,
} from '../../coopcycle-frontend-js/logistics/redux'
import webReducers from './reducers'
import webTaskEntityReducers from './taskEntityReducers'
import webTaskListEntityReducers from './taskListEntityReducers'
import webUiReducers from './uiReducers'
import configReducers from './configReducers'
import filtersReducers from './filtersReducers'
import trackingReducers from './trackingReducers'

const middlewares = [ thunk, socketIO, persistFilters ]

// we maye want enhancing redux dev tools only  in dev ?
// also if server side render is made later, it is
// better to add a guard here
const composeEnhancers = (typeof window !== 'undefined' &&
  window.__REDUX_DEVTOOLS_EXTENSION_COMPOSE__) || compose

const reducer = (state, action) => {
  //todo move more properties from webReducers inside `logistics` state
  let rootState = webReducers(state, action)

  let logisticsState = combineReducers({
    date: dateReducer,
    entities: combineReducers({
      tasks: reduceReducers(coreTaskEntityReducers, webTaskEntityReducers),
      taskLists: reduceReducers(coreTaskListEntityReducers, webTaskListEntityReducers),
    }),
    ui: reduceReducers(coreUiReducers, webUiReducers)
  })(state.logistics, action)

  return {
    ...rootState,
    logistics: logisticsState,
    config: configReducers(state.config, action),
    settings: filtersReducers(state.settings, action),
    tracking: trackingReducers(state.tracking, action),
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
