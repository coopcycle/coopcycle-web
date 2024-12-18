import { configureStore } from '@reduxjs/toolkit'
import { accountSlice } from '../../../entities/account/reduxSlice'
import { orderSlice } from '../../../entities/order/reduxSlice'
import { apiSlice } from '../../../api/slice'

export function createStoreFromPreloadedState(preloadedState) {
  return configureStore({
    reducer: {
      [accountSlice.name]: accountSlice.reducer,
      [orderSlice.name]: orderSlice.reducer,
      [apiSlice.reducerPath]: apiSlice.reducer,
    },
    preloadedState,
    middleware: getDefaultMiddleware => getDefaultMiddleware()
      .concat(apiSlice.middleware),
  })
}
