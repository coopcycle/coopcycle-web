import {createStore, applyMiddleware, compose, combineReducers} from 'redux'
import thunk from 'redux-thunk'
import reduceReducers from 'reduce-reducers';
import { socketIO, persistFilters } from './middlewares'
import {
  dateReducer,
  taskEntityReducers as coreTaskEntityReducers,
  taskListEntityReducers as coreTaskListEntityReducers,
  uiReducers as coreUiReducers,
  tourEntityReducers as coreTourEntityReducers,
} from '../../coopcycle-frontend-js/logistics/redux'
import * as webReducers from './reducers'
import webTaskEntityReducers from './taskEntityReducers'
import webTaskListEntityReducers from './taskListEntityReducers'
import webUiReducers from './uiReducers'
import configReducers from './configReducers'
import settingsReducers from './settingsReducers'
import trackingReducers from './trackingReducers'
import tourEntityReducers from './tourEntityReducers';
import organizationEntityReducers from './organizationEntityReducers';

const middlewares = [ thunk, socketIO, persistFilters ]

// we maye want enhancing redux dev tools only  in dev ?
// also if server side render is made later, it is
// better to add a guard here
const composeEnhancers = (typeof window !== 'undefined' &&
  window.__REDUX_DEVTOOLS_EXTENSION_COMPOSE__) || compose

const reducer = combineReducers({
  ...webReducers,
  logistics: combineReducers({
    date: dateReducer,
    entities: combineReducers({
      tasks: reduceReducers(coreTaskEntityReducers, webTaskEntityReducers),
      taskLists: reduceReducers(coreTaskListEntityReducers, webTaskListEntityReducers),
      tours: reduceReducers(coreTourEntityReducers, tourEntityReducers),
      organizations: reduceReducers(organizationEntityReducers)
    }),
    ui: reduceReducers(coreUiReducers, webUiReducers)
  }),
  config: configReducers,
  settings: settingsReducers,
  tracking: trackingReducers,
})

export const createStoreFromPreloadedState = preloadedState => {
  return createStore(
    reducer,
    preloadedState,
    composeEnhancers(
      applyMiddleware(...middlewares)
    )
  )
}
