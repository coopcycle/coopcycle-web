import React, { useEffect } from 'react'
import { Provider } from 'react-redux'
import { accountSlice } from '../../entities/account/reduxSlice'
import DeliveryForm from './DeliveryForm.js'
import { createStoreFromPreloadedState } from './redux/store'
import Modal from 'react-modal'
import { RootWithDefaults } from '../../utils/react'
import { Mode } from './Mode'

const buildInitialState = () => {
  return {
    [accountSlice.name]: accountSlice.getInitialState(),
  }
}

const store = createStoreFromPreloadedState(buildInitialState())

export default function ({
  storeNodeId,
  deliveryId,
  deliveryNodeId,
  delivery,
  isDispatcher,
  isDebugPricing,
}) {
  useEffect(() => {
    Modal.setAppElement('.content')
  }, [])

  return (
    <RootWithDefaults>
      <Provider store={store}>
        <DeliveryForm
          mode={
            Boolean(deliveryNodeId)
              ? Mode.DELIVERY_UPDATE
              : Mode.DELIVERY_CREATE
          }
          storeNodeId={storeNodeId}
          deliveryId={deliveryId}
          deliveryNodeId={deliveryNodeId}
          preLoadedDeliveryData={delivery ? JSON.parse(delivery) : null}
          isDispatcher={isDispatcher}
          isDebugPricing={isDebugPricing}
        />
      </Provider>
    </RootWithDefaults>
  )
}
