import { configureStore } from '@reduxjs/toolkit'
import { accountSlice } from '../../../entities/account/reduxSlice'
import { apiSlice } from '../../../api/slice'
import { pricingSlice } from './pricingSlice'

export function createStoreFromPreloadedState(preloadedState) {
  return configureStore({
    reducer: {
      [accountSlice.name]: accountSlice.reducer,
      [apiSlice.reducerPath]: apiSlice.reducer,
      [pricingSlice.name]: pricingSlice.reducer,
    },
    preloadedState,
    middleware: getDefaultMiddleware =>
      getDefaultMiddleware().concat(apiSlice.middleware),
  })
}
