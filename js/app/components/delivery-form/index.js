import React, { useEffect, useLayoutEffect } from 'react'
import { Provider, useDispatch } from 'react-redux'
import { accountSlice } from '../../entities/account/reduxSlice'
import DeliveryForm from './DeliveryForm.js'
import { createStoreFromPreloadedState } from './redux/store'
import Modal from 'react-modal'
import { RootWithDefaults } from '../../utils/react'
import { Mode } from './mode'
import { setMode } from './redux/formSlice'

const buildInitialState = () => {
  return {
    [accountSlice.name]: accountSlice.getInitialState(),
  }
}

const store = createStoreFromPreloadedState(buildInitialState())

const Form = ({
  storeNodeId,
  deliveryId,
  deliveryNodeId,
  delivery,
  isDispatcher,
  isDebugPricing,
}) => {
  const dispatch = useDispatch()

  useLayoutEffect(() => {
    dispatch(
      setMode(
        Boolean(deliveryNodeId) ? Mode.DELIVERY_UPDATE : Mode.DELIVERY_CREATE,
      ),
    )
  }, [dispatch, deliveryNodeId])

  return (
    <DeliveryForm
      storeNodeId={storeNodeId}
      deliveryId={deliveryId}
      deliveryNodeId={deliveryNodeId}
      preLoadedDeliveryData={delivery ? JSON.parse(delivery) : null}
      isDispatcher={isDispatcher}
      isDebugPricing={isDebugPricing}
    />
  )
}

export default function (props) {
  useEffect(() => {
    Modal.setAppElement('.content')
  }, [])

  return (
    <RootWithDefaults>
      <Provider store={store}>
        <Form {...props} />
      </Provider>
    </RootWithDefaults>
  )
}
