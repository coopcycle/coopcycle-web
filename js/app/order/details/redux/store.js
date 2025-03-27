import { configureStore, createListenerMiddleware } from '@reduxjs/toolkit'
import { accountSlice } from '../../../entities/account/reduxSlice'
import { guestSlice } from '../../../entities/guest/reduxSlice'
import {
  orderSlice,
  selectOrderNodeId,
  setOrderEvents,
} from '../../../entities/order/reduxSlice'
import { apiSlice } from '../../../api/slice'

const listenerMiddleware = createListenerMiddleware()
listenerMiddleware.startListening({
  matcher: apiSlice.endpoints.getOrder.matchFulfilled,
  effect: async (action, listenerApi) => {
    const payload = action.payload

    // update the list of order events with the latest data from the backend
    if (
      payload &&
      payload['@id'] === selectOrderNodeId(listenerApi.getState())
    ) {
      listenerApi.dispatch(setOrderEvents(payload.events))
    }
  },
})

export function createStoreFromPreloadedState(preloadedState) {
  return configureStore({
    reducer: {
      [accountSlice.name]: accountSlice.reducer,
      [guestSlice.name]: guestSlice.reducer,
      [apiSlice.reducerPath]: apiSlice.reducer,
      [orderSlice.name]: orderSlice.reducer,
    },
    preloadedState,
    middleware: getDefaultMiddleware =>
      getDefaultMiddleware()
        .concat(apiSlice.middleware)
        .prepend(listenerMiddleware.middleware),
  })
}
