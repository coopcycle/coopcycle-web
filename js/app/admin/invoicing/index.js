import React from 'react'
import { Provider } from 'react-redux'
import { createStoreFromPreloadedState } from '../../order/redux/store'
import { accountSlice } from '../../entities/account/reduxSlice'
import { orderSlice } from '../../entities/order/reduxSlice'
import OrdersToInvoice from './components/OrdersToInvoice'

const buildInitialState = () => {
  // const shippingTimeRange = JSON.parse(orderDataElement.dataset.orderShippingTimeRange || null)
  // const persistedTimeRange = JSON.parse(window.sessionStorage.getItem(getTimingPathForStorage(orderNodeId)))

  return {
    [accountSlice.name]: accountSlice.getInitialState(),
    [orderSlice.name]: {
      ...orderSlice.getInitialState(),
      // '@id': orderNodeId,
      // shippingTimeRange: shippingTimeRange,
    },
  }
}

const store = createStoreFromPreloadedState(buildInitialState())

export default () => {
  return (
    <Provider store={store}>
      <OrdersToInvoice />
    </Provider>
  )
}
