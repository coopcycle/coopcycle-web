import { configureStore } from '@reduxjs/toolkit'
import { timeRangeSlice } from '../../components/order/timeRange/reduxSlice'
import { accountSlice } from '../../entities/account/reduxSlice'
import { guestSlice } from '../../entities/guest/reduxSlice'
import { orderSlice } from '../../entities/order/reduxSlice'
import { apiSlice } from '../../api/slice'

export function createStoreFromPreloadedState(preloadedState) {
  return configureStore({
    reducer: {
      [accountSlice.name]: accountSlice.reducer,
      [guestSlice.name]: guestSlice.reducer,
      [orderSlice.name]: orderSlice.reducer,
      [timeRangeSlice.name]: timeRangeSlice.reducer,
      [apiSlice.reducerPath]: apiSlice.reducer,
    },
    preloadedState,
    middleware: getDefaultMiddleware =>
      getDefaultMiddleware().concat(apiSlice.middleware),
  })
}
