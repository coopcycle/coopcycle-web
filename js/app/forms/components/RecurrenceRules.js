import React from 'react'
import { useDispatch, useSelector } from 'react-redux'

import Modal from 'react-modal'
import RecurrenceRuleModalContent from './RecurrenceRuleModalContent'
import RecurrenceRule from './RecurrenceRule'
import {
  closeRecurrenceRuleModal,
  openNewRecurrenceRuleModal,
  selectIsRecurrenceRuleModalOpen,
  selectRecurrenceRule,
} from '../redux/recurrenceRulesSlice'
import { useTranslation } from 'react-i18next'

function Content() {
  const recurrenceRule = useSelector(selectRecurrenceRule)

  const { t } = useTranslation()
  const dispatch = useDispatch()

  if (!recurrenceRule) {
    return (
      <a
        href="#"
        className="mr-3"
        onClick={e => {
          e.preventDefault()
          dispatch(openNewRecurrenceRuleModal())
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
      onClick={() => dispatch(openNewRecurrenceRuleModal())}
    />
  )
}

export function RecurrenceRules() {
  const recurrenceRuleModalIsOpen = useSelector(selectIsRecurrenceRuleModalOpen)

  const dispatch = useDispatch()

  return (
    <div>
      <Content />
      <Modal
        isOpen={recurrenceRuleModalIsOpen}
        onRequestClose={() => dispatch(closeRecurrenceRuleModal())}
        className="ReactModal__Content--recurrence"
        overlayClassName="ReactModal__Overlay--zIndex-1001"
        shouldCloseOnOverlayClick={true}>
        <RecurrenceRuleModalContent />
      </Modal>
    </div>
  )
}
