import React, { useEffect, useLayoutEffect } from 'react'
import { Provider, useDispatch } from 'react-redux'
import { accountSlice } from '../../entities/account/reduxSlice'
import DeliveryForm from './DeliveryForm'
import { createStoreFromPreloadedState } from './redux/store'
import Modal from 'react-modal'
import { RootWithDefaults } from '../../utils/react'
import { Mode } from './mode'
import { setMode } from './redux/formSlice'
import FlagsContext from './FlagsContext'
import { Uri } from '../../api/types'

const buildInitialState = () => {
  return {
    [accountSlice.name]: accountSlice.getInitialState(),
  }
}

const store = createStoreFromPreloadedState(buildInitialState())

type Props = {
  storeNodeId: Uri
  deliveryId?: number
  deliveryNodeId?: Uri
  delivery?: string
  isDispatcher: boolean
  isDebugPricing: boolean
  isPriceBreakdownEnabled: boolean
}

const Form = ({
  storeNodeId,
  deliveryId,
  deliveryNodeId,
  delivery,
  isDispatcher,
  isDebugPricing,
  isPriceBreakdownEnabled,
}: Props) => {
  const dispatch = useDispatch()

  useLayoutEffect(() => {
    dispatch(
      setMode(
        Boolean(deliveryNodeId) ? Mode.DELIVERY_UPDATE : Mode.DELIVERY_CREATE,
      ),
    )
  }, [dispatch, deliveryNodeId])

  return (
    <FlagsContext.Provider
      value={{ isDispatcher, isDebugPricing, isPriceBreakdownEnabled }}>
      <DeliveryForm
        storeNodeId={storeNodeId}
        deliveryId={deliveryId}
        deliveryNodeId={deliveryNodeId}
        preLoadedDeliveryData={delivery ? JSON.parse(delivery) : null}
      />
    </FlagsContext.Provider>
  )
}

export default function (props: Props) {
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
