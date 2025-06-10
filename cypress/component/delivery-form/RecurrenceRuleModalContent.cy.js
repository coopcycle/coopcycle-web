import React from 'react'
import { Provider } from 'react-redux'
// import styles and common widgets
import common from '../../../js/app/common'
import ModalContent from '../../../js/app/components/delivery-form/RecurrenceRuleModalContent'
import * as hookModule from '../../../js/app/components/delivery-form/hooks/useDeliveryFormFormikContext'
import { createStoreFromPreloadedState } from '../../../js/app/components/delivery-form/redux/store'

it('mounts', () => {
  cy.stub(hookModule, 'useDeliveryFormFormikContext').returns({
    rruleValue: 'FREQ=WEEKLY;BYDAY=MO',
    setFieldValue: cy.stub().as('setFieldValueStub'),
  })

  const reduxStore = createStoreFromPreloadedState()

  cy.mount(
    <Provider store={reduxStore}>
      <ModalContent />
    </Provider>,
  )

  cy.get('.ant-checkbox-input').each(($checkbox, index) => {
    if (index === 0) {
      cy.wrap($checkbox).should('be.checked')
    } else {
      cy.wrap($checkbox).should('not.be.checked')
    }
  })
})
