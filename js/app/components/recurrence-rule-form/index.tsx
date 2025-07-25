import React from 'react'
import { Provider } from 'react-redux'
import { accountSlice } from '../../entities/account/reduxSlice'
import DeliveryForm from '../delivery-form/DeliveryForm.js'
//FIXME: temporary re-use of the delivery-form store, to be replaced with a dedicated store
import { createStoreFromPreloadedState } from '../delivery-form/redux/store'
import Modal from 'react-modal'
import { createRoot } from 'react-dom/client'
import { Mode } from '../delivery-form/mode'
import { formSlice } from '../delivery-form/redux/formSlice'
import { RootWithDefaults } from '../../utils/react'

const buildInitialState = () => {
  return {
    [accountSlice.name]: accountSlice.getInitialState(),
    [formSlice.name]: {
      ...formSlice.getInitialState(),
      mode: Mode.RECURRENCE_RULE_UPDATE,
    },
  }
}

const store = createStoreFromPreloadedState(buildInitialState())

// Mount the component to the DOM when the document is loaded
document.addEventListener('DOMContentLoaded', () => {
  const container = document.getElementById('recurrence-rule-form')
  if (container) {
    const storeNodeId = container.dataset.storeNodeId
    const recurrenceRuleId = container.dataset.recurrenceRuleId
    const recurrenceRuleNodeId = container.dataset.recurrenceRuleNodeId

    const preLoadedDeliveryData = container.dataset.delivery
      ? JSON.parse(container.dataset.delivery)
      : null
    preLoadedDeliveryData.rrule = container.dataset.recurrenceRule

    const isDispatcher = container.dataset.isDispatcher === 'true'
    const isDebugPricing = container.dataset.isDebugPricing === 'true'

    Modal.setAppElement('.content')

    const root = createRoot(container)
    root.render(
      <RootWithDefaults>
        <Provider store={store}>
          <DeliveryForm
            storeNodeId={storeNodeId}
            //FIXME; might lead to bugs
            deliveryId={recurrenceRuleId}
            //FIXME; might lead to bugs
            deliveryNodeId={recurrenceRuleNodeId}
            preLoadedDeliveryData={preLoadedDeliveryData}
            isDispatcher={isDispatcher}
            isDebugPricing={isDebugPricing}
          />
        </Provider>
      </RootWithDefaults>,
    )
  }
})
