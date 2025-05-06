import React, { StrictMode } from 'react'
import { Provider } from 'react-redux'
import { ConfigProvider } from 'antd'
import { antdLocale } from '../../i18n'
import { accountSlice } from '../../entities/account/reduxSlice'
import { createStoreFromPreloadedState } from '../../delivery/pricing/redux/store'
import DeliveryForm from './DeliveryForm.js'

const buildInitialState = () => {
  return {
    [accountSlice.name]: accountSlice.getInitialState(),
  }
}

const store = createStoreFromPreloadedState(buildInitialState())

export default function ({
  storeId,
  deliveryId,
  order,
  isDispatcher,
  isDebugPricing,
}) {
  return (
    <StrictMode>
      <Provider store={store}>
        <ConfigProvider locale={antdLocale}>
          <DeliveryForm
            storeId={storeId}
            deliveryId={deliveryId}
            order={order}
            isDispatcher={isDispatcher}
            isDebugPricing={isDebugPricing}
          />
        </ConfigProvider>
      </Provider>
    </StrictMode>
  )
}
