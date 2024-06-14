import { configureStore } from '@reduxjs/toolkit'
import { orderSlice } from './orderSlice'
import { uiSlice } from './uiSlice'
import { accountSlice } from '../../redux/account'
import { guestSlice } from '../../redux/guest'
import { apiSlice } from '../../redux/api/slice'

export function createStoreFromPreloadedState(preloadedState) {
  return configureStore({
    reducer: {
      account: accountSlice.reducer,
      guest: guestSlice.reducer,
      order: orderSlice.reducer,
      ui: uiSlice.reducer,
      [apiSlice.reducerPath]: apiSlice.reducer,
    },
    preloadedState,
    middleware: getDefaultMiddleware =>
      getDefaultMiddleware().concat(apiSlice.middleware),
  })
}
