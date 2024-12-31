import React from 'react'
import { Provider } from 'react-redux'
import { createStoreFromPreloadedState } from '../../order/redux/store'
import { accountSlice } from '../../entities/account/reduxSlice'
import { orderSlice } from '../../entities/order/reduxSlice'
import OrdersToInvoice from './components/OrdersToInvoice'
import { TopNav } from '../../components/TopNav'
import { useTranslation } from 'react-i18next'

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
  const { t } = useTranslation()

  return (
    <Provider store={store}>
      <TopNav>{t('ADMIN_INVOICING_TITLE')}</TopNav>
      <OrdersToInvoice />
    </Provider>
  )
}
