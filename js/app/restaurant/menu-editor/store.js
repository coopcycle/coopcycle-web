import { createStore, applyMiddleware, compose, combineReducers } from 'redux'
import { thunk } from 'redux-thunk'
import { apiSlice } from '../../api/slice'
import menuReducers from './menu-reducers'
import productsReducers from './products-reducers'

const middlewares = [ thunk, apiSlice.middleware ]

// we maye want enhancing redux dev tools only  in dev ?
// also if server side render is made later, it is
// better to add a guard here
const composeEnhancers = (typeof window !== 'undefined' &&
  window.__REDUX_DEVTOOLS_EXTENSION_COMPOSE__) || compose

const reducer = combineReducers({
  menu: menuReducers,
  products: productsReducers,
  [apiSlice.reducerPath]: apiSlice.reducer,
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
