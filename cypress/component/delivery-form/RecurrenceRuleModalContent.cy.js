import React from 'react'
import { Provider } from 'react-redux'
// import styles and common widgets
import common from '../../../js/app/common'
import ModalContent from '../../../js/app/components/delivery-form/RecurrenceRuleModalContent'
import * as hookModule from '../../../js/app/components/delivery-form/hooks/useDeliveryFormFormikContext'
import { createStoreFromPreloadedState } from '../../../js/app/components/delivery-form/redux/store'
import moment from 'moment'

it('displays a default rule', () => {
  cy.stub(hookModule, 'useDeliveryFormFormikContext').returns({
    rruleValue: null,
    setFieldValue: cy.stub(),
  })

  const reduxStore = createStoreFromPreloadedState()

  cy.mount(
    <Provider store={reduxStore}>
      <ModalContent />
    </Provider>,
  )

  const todayIndex = (new Date().getDay() + 6) % 7 // 0 (Monday) to 6 (Sunday)

  cy.get('.ant-checkbox-input').each(($checkbox, index) => {
    if (index === todayIndex) {
      cy.wrap($checkbox).should('be.checked')
    } else {
      cy.wrap($checkbox).should('not.be.checked')
    }
  })
})

it('displays a standard rule', () => {
  cy.stub(hookModule, 'useDeliveryFormFormikContext').returns({
    rruleValue: 'FREQ=WEEKLY;BYDAY=MO',
    setFieldValue: cy.stub(),
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

it('displays a custom rule', () => {
  cy.stub(hookModule, 'useDeliveryFormFormikContext').returns({
    rruleValue: 'RRULE:FREQ=WEEKLY;INTERVAL=2;WKST=MO;BYDAY=MO',
    setFieldValue: cy.stub(),
  })

  const reduxStore = createStoreFromPreloadedState()

  cy.mount(
    <Provider store={reduxStore}>
      <ModalContent />
    </Provider>,
  )

  cy.get('.ant-checkbox-input').each(($checkbox, index) => {
    cy.wrap($checkbox).should('not.be.visible')
  })

  cy.get('[data-testid="recurrence-override-rule-checkbox"]').should(
    'be.checked',
  )
  cy.get('[data-testid="recurrence-override-rule-input"]').should('be.visible')
  cy.get('[data-testid="recurrence-override-rule-input"]').should(
    'have.value',
    'RRULE:FREQ=WEEKLY;INTERVAL=2;WKST=MO;BYDAY=MO',
  )
})

it('switch to a custom rule', () => {
  cy.stub(hookModule, 'useDeliveryFormFormikContext').returns({
    rruleValue: 'FREQ=WEEKLY;BYDAY=MO',
    setFieldValue: cy.spy().as('setFieldValue'),
  })

  const reduxStore = createStoreFromPreloadedState()

  cy.mount(
    <Provider store={reduxStore}>
      <ModalContent />
    </Provider>,
  )

  cy.get('.ant-collapse-header').click()

  cy.get('[data-testid="recurrence-override-rule-checkbox"]').check()

  cy.get('[data-testid="recurrence-override-rule-input"]').clear()
  cy.get('[data-testid="recurrence-override-rule-input"]').type(
    'RRULE:FREQ=WEEKLY;INTERVAL=2;WKST=MO;BYDAY=MO',
  )

  cy.get('[data-testid="save"]').click()

  cy.get('@setFieldValue').should(
    'have.been.calledWith',
    'rrule',
    'RRULE:FREQ=WEEKLY;INTERVAL=2;WKST=MO;BYDAY=MO',
  )
})

it('switch to a standard rule', () => {
  cy.stub(hookModule, 'useDeliveryFormFormikContext').returns({
    rruleValue: 'RRULE:FREQ=WEEKLY;INTERVAL=2;WKST=MO;BYDAY=MO',
    setFieldValue: cy.spy().as('setFieldValue'),
  })

  const reduxStore = createStoreFromPreloadedState()

  cy.mount(
    <Provider store={reduxStore}>
      <ModalContent />
    </Provider>,
  )

  cy.get('[data-testid="recurrence-override-rule-checkbox"]').uncheck()

  const todayIndex = (new Date().getDay() + 6) % 7 // 0 (Monday) to 6 (Sunday)
  const defaultRule =
    'FREQ=WEEKLY;BYDAY=' + moment().locale('en').format('dd').toUpperCase()

  cy.get('.ant-checkbox-input').each(($checkbox, index) => {
    if (index === todayIndex) {
      cy.wrap($checkbox).should('be.checked')
    } else {
      cy.wrap($checkbox).should('not.be.checked')
    }
  })

  cy.get('[data-testid="recurrence-override-rule-input"]').should(
    'have.value',
    defaultRule,
  )

  cy.get('[data-testid="save"]').click()

  cy.get('@setFieldValue').should('have.been.calledWith', 'rrule', defaultRule)
})
