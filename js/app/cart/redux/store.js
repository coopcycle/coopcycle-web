import { createStore, applyMiddleware, compose } from 'redux'
import thunk from 'redux-thunk'
import reducer from './reducers'

const middlewares = [ thunk ]

export const createStoreFromPreloadedState = preloadedState => {
  return createStore(
    reducer,
    preloadedState,
    compose(
      applyMiddleware(...middlewares)
    )
  )
}
