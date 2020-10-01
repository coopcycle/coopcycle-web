import { createStore, applyMiddleware, compose } from 'redux'
import thunk from 'redux-thunk'
import { reducer as coreReducer } from 'coopcycle-frontend-js/dispatch/redux'
import webReducer from './reducers'
import webDispatchReducer from './dispatchReducers'
import reduceReducers from 'reduce-reducers';
import { socketIO, persistFilters } from './middlewares'

const middlewares = [ thunk, socketIO, persistFilters ]

// we maye want enhancing redux dev tools only  in dev ?
// also if server side render is made later, it is
// better to add a guard here
const composeEnhancers = (typeof window !== 'undefined' &&
  window.__REDUX_DEVTOOLS_EXTENSION_COMPOSE__) || compose

const reducer = (state, action) => {
  const rootState = webReducer(state, action)
  const dispatchState = reduceReducers(coreReducer, webDispatchReducer)(state.dispatch, action)

  return {
    ...rootState,
    dispatch: dispatchState,
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
