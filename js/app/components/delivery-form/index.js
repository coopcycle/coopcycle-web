import React, { StrictMode, useEffect } from 'react'
import { Provider } from 'react-redux'
import { ConfigProvider } from 'antd'
import { antdLocale } from '../../i18n'
import { accountSlice } from '../../entities/account/reduxSlice'
import DeliveryForm from './DeliveryForm.js'
import { createStoreFromPreloadedState } from './redux/store'
import Modal from 'react-modal'

const buildInitialState = () => {
  return {
    [accountSlice.name]: accountSlice.getInitialState(),
  }
}

const store = createStoreFromPreloadedState(buildInitialState())

export default function ({
  storeId,
  storeNodeId,
  deliveryId,
  deliveryNodeId,
  order,
  isDispatcher,
  isDebugPricing,
}) {
  useEffect(() => {
    Modal.setAppElement('.content');
  }, [])

  return (
    <StrictMode>
      <Provider store={store}>
        <ConfigProvider locale={antdLocale}>
          <DeliveryForm
            storeId={storeId}
            storeNodeId={storeNodeId}
            deliveryId={deliveryId}
            deliveryNodeId={deliveryNodeId}
            order={order}
            isDispatcher={isDispatcher}
            isDebugPricing={isDebugPricing}
          />
        </ConfigProvider>
      </Provider>
    </StrictMode>
  )
}
