import { createStore, applyMiddleware, compose } from 'redux'
import thunk from 'redux-thunk'
import ReduxAsyncQueue from 'redux-async-queue'
import reducer from './reducers'

const middlewares = [ thunk, ReduxAsyncQueue ]

export const createStoreFromPreloadedState = preloadedState => {
  return createStore(
    reducer,
    preloadedState,
    compose(
      applyMiddleware(...middlewares)
    )
  )
}
