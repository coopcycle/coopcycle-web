import { getTimingPathForStorage } from '../../utils/order/helpers'
import Modal from 'react-modal'
import { createRoot } from 'react-dom/client'
import { Provider } from 'react-redux'
import { I18nextProvider } from 'react-i18next'
import i18n from '../../i18n'
import { createPortal } from 'react-dom'
import React from 'react'
import TimeRangeChangedModal
  from '../../components/order/timeRange/TimeRangeChangedModal'
import TimeRange from '../../components/order/timeRange/TimeRange'
import { accountSlice } from '../../entities/account/reduxSlice'
import { guestSlice } from '../../entities/guest/reduxSlice'
import { buildGuestInitialState } from '../../entities/guest/utils'
import { orderSlice } from '../../entities/order/reduxSlice'
import { timeRangeSlice } from '../../components/order/timeRange/reduxSlice'
import { createStoreFromPreloadedState } from './redux/store'

import '../../components/order/index.scss'

const orderDataElement = document.querySelector('#js-order-data')
const orderNodeId = orderDataElement.dataset.orderNodeId
const orderAccessToken = orderDataElement.dataset.orderAccessToken

const buildInitialState = () => {
  const shippingTimeRange = JSON.parse(orderDataElement.dataset.orderShippingTimeRange || null)
  const persistedTimeRange = JSON.parse(window.sessionStorage.getItem(getTimingPathForStorage(orderNodeId)))

  return {
    [accountSlice.name]: accountSlice.getInitialState(),
    [guestSlice.name]: buildGuestInitialState(orderNodeId, orderAccessToken),
    [orderSlice.name]: {
      ...orderSlice.getInitialState(),
      '@id': orderNodeId,
      shippingTimeRange: shippingTimeRange,
    },
    [timeRangeSlice.name]: {
      ...timeRangeSlice.getInitialState(),
      persistedTimeRange: persistedTimeRange,
    }
  }
}

const store = createStoreFromPreloadedState(buildInitialState())

// used by the PaymentForm
window._rootStore = store

const container = document.getElementById('react-root')

const fulfilmentTimeRangeContainer = document.getElementById('order__fulfilment_time_range__container')

Modal.setAppElement(container)

const root = createRoot(container);
root.render(
  <Provider store={ store }>
    <I18nextProvider i18n={ i18n }>
      {createPortal(<TimeRange />, fulfilmentTimeRangeContainer) }
      <TimeRangeChangedModal />
    </I18nextProvider>
  </Provider>
)
