import { createStore, applyMiddleware, compose } from 'redux'
import thunk from 'redux-thunk'

import reducers, { initialState } from './reducers'

const middlewares = [ thunk ]

const composeEnhancers = (typeof window !== 'undefined' &&
  window.__REDUX_DEVTOOLS_EXTENSION_COMPOSE__) || compose

export const createStoreFromPreloadedState = preloadedState => {
  return createStore(
    reducers,
    {
      ...initialState,
      ...preloadedState,
    },
    composeEnhancers(
      applyMiddleware(...middlewares)
    )
  )
}
