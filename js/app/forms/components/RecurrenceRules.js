import React from 'react'
import { useDispatch, useSelector } from 'react-redux'

import Modal from 'react-modal'
import RecurrenceRuleModalContent from './RecurrenceRuleModalContent'
import RecurrenceRule from './RecurrenceRule'
import {
  closeRecurrenceModal,
  openRecurrenceModal,
  selectIsCancelled,
  selectIsRecurrenceModalOpen,
  selectRecurrenceRule,
} from '../redux/recurrenceSlice'
import { useTranslation } from 'react-i18next'

function Content() {
  const recurrenceRule = useSelector(selectRecurrenceRule)
  const isCancelled = useSelector(selectIsCancelled);

  const { t } = useTranslation()
  const dispatch = useDispatch()

  if (isCancelled) {
    return (
      <div className="text-muted">
        <i className="fa fa-ban"></i>
        &nbsp;
        {t('SUBSCRIPTION_CANCELLED')}
      </div>
    )
  }

  if (!recurrenceRule) {
    return (
      <a
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

  return (
    <div>
      <Content />
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
