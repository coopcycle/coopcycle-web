import { createStore, applyMiddleware, compose } from 'redux'
import thunk from 'redux-thunk'
import reducers from './reducers'
import { socketIO } from './middlewares'

const middlewares = [ thunk, socketIO ]

export const createStoreFromPreloadedState = preloadedState => {
  return createStore(
    reducers,
    preloadedState,
    compose(
      applyMiddleware(...middlewares)
    )
  )
}
