import { createStore, applyMiddleware, compose } from 'redux'
import thunk from 'redux-thunk'
import reducers from './reducers'

const middlewares = [ thunk ]

export const createStoreFromPreloadedState = preloadedState => {
  return createStore(
    reducers,
    preloadedState,
    compose(
      applyMiddleware(...middlewares)
    )
  )
}
