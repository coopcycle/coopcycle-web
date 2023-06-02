import {applyMiddleware, compose, createStore} from 'redux'
import thunk from 'redux-thunk'
import ReduxAsyncQueue from 'redux-async-queue'
import reducer from './reducers'
import {playerWebsocket, updateFormElements} from './middlewares'

const middlewares = [ updateFormElements, playerWebsocket, thunk, ReduxAsyncQueue ]

const composeEnhancers = (typeof window !== 'undefined' && window.__REDUX_DEVTOOLS_EXTENSION_COMPOSE__) || compose

export const createStoreFromPreloadedState = preloadedState => {
  return createStore(
    reducer,
    preloadedState,
    composeEnhancers(
      applyMiddleware(...middlewares)
    )
  )
}
