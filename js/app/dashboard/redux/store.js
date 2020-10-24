import { createStore, applyMiddleware, compose } from 'redux'
import thunk from 'redux-thunk'
import reduceReducers from 'reduce-reducers';
import { socketIO, persistFilters } from './middlewares'
import {
  reducer as coreReducer,
  taskEntityReducers as coreTaskEntityReducers,
  taskListEntityReducers as coreTaskListEntityReducers,
  uiReducers as coreUiReducers,
} from '../../coopcycle-frontend-js/logistics/redux'
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
  let rootState = webReducers(state, action)

  let logisticsState = coreReducer(state.logistics, action)
  let taskEntityState =  reduceReducers(coreTaskEntityReducers, webTaskEntityReducers)(state.logistics.entities.tasks, action)
  let taskListEntityState =  reduceReducers(coreTaskListEntityReducers, webTaskListEntityReducers)(state.logistics.entities.taskLists, action)
  let uiState = reduceReducers(coreUiReducers, webUiReducers)(state.logistics.ui, action)

  return {
    ...rootState,
    logistics: {
      ...logisticsState,
      entities: {
        tasks: taskEntityState,
        taskLists: taskListEntityState,
      },
      ui: uiState
    }
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
