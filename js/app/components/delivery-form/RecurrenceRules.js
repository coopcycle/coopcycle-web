import React from 'react'
import { useDispatch, useSelector } from 'react-redux'

import Modal from 'react-modal'
import RecurrenceRuleModalContent from './RecurrenceRuleModalContent'
import RecurrenceRule from './RecurrenceRule'
import {
  closeRecurrenceModal,
  openRecurrenceModal,
  selectIsRecurrenceModalOpen,
} from './redux/recurrenceSlice'
import { useTranslation } from 'react-i18next'
import { useDeliveryFormFormikContext } from './hooks/useDeliveryFormFormikContext'

import './RecurrenceRules.scss'

function Content() {
  const { rruleValue: recurrenceRule } = useDeliveryFormFormikContext()

  const { t } = useTranslation()
  const dispatch = useDispatch()

  // FIXME: Uncomment this when this component is used on the recurrence rule page
  // if (isCancelled) {
  //   return (
  //     <div className="text-muted">
  //       <i className="fa fa-ban"></i>
  //       &nbsp;
  //       {t('SUBSCRIPTION_CANCELLED')}
  //     </div>
  //   )
  // }

  if (!recurrenceRule) {
    return (
      <a
        data-testid="recurrence-add"
        href="#"
        className="mr-3"
        onClick={e => {
          e.preventDefault()
          dispatch(openRecurrenceModal())
        }}>
        <i className="fa fa-clock-o"></i>
        &nbsp;
        {t('RECURRENCE_RULE_ADD')}
      </a>
    )
  }

  return (
    <RecurrenceRule
      rrule={recurrenceRule}
      onClick={() => dispatch(openRecurrenceModal())}
    />
  )
}

export function RecurrenceRules() {
  const recurrenceModalIsOpen = useSelector(selectIsRecurrenceModalOpen)

  const dispatch = useDispatch()

  const { t } = useTranslation()

  return (
    <div>
      <div>
        <h4>{t('DELIVERY_FORM_RECURRENCE_RULE')}</h4>
        <Content />
      </div>
      <Modal
        isOpen={recurrenceModalIsOpen}
        onRequestClose={() => dispatch(closeRecurrenceModal())}
        className="ReactModal__Content--recurrence"
        overlayClassName="ReactModal__Overlay--zIndex-1001"
        shouldCloseOnOverlayClick={true}>
        <RecurrenceRuleModalContent />
      </Modal>
    </div>
  )
}
