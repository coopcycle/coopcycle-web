import { createStore, applyMiddleware, compose } from 'redux'
import thunk from 'redux-thunk'
import reducers from './reducers'
import { socketIO, title } from './middlewares'

const middlewares = [ thunk, socketIO, title ]

export const createStoreFromPreloadedState = preloadedState => {
  return createStore(
    reducers,
    preloadedState,
    compose(
      applyMiddleware(...middlewares)
    )
  )
}
