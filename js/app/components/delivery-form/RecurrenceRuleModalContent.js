import React from 'react'
import { useDispatch } from 'react-redux'
import { RRule, rrulestr } from 'rrule'
import _ from 'lodash'
import Select from 'react-select'
import { Button, Checkbox } from 'antd'
import moment from 'moment'
import { Formik } from 'formik'
import { useTranslation } from 'react-i18next'
import classNames from 'classnames'

import TimeRange from '../../utils/TimeRange'
import RecurrenceRuleAsText from './RecurrenceRuleAsText'
import { closeRecurrenceModal } from './redux/recurrenceSlice'
import { useDeliveryFormFormikContext } from './hooks/useDeliveryFormFormikContext'

const freqOptions = [
  { value: RRule.DAILY, label: 'Every day' },
  { value: RRule.WEEKLY, label: 'Every week' },
]

const locale = $('html').attr('lang')
const weekdays = TimeRange.weekdaysShort(locale)

const byDayOptions = weekdays.map(weekday => ({
  label: weekday.name,
  value: RRule[weekday.key.toUpperCase()].weekday,
}))

const RecurrenceEditor = ({ recurrence, onChange }) => {
  const ruleObj = rrulestr(recurrence)
  const defaultValue = _.find(
    freqOptions,
    option => option.value === ruleObj.options.freq,
  )

  return (
    <div>
      {/* It is not possible to change FREQ atm */}
      <div className="mb-4 d-none">
        <Select
          options={freqOptions}
          defaultValue={defaultValue}
          onChange={option =>
            onChange({ ...ruleObj.options, freq: option.value })
          }
        />
      </div>
      <div className="mb-2">
        <Checkbox.Group
          options={byDayOptions}
          defaultValue={ruleObj.options.byweekday}
          onChange={opts => onChange({ ...ruleObj.options, byweekday: opts })}
        />
      </div>
      <div>
        <small className="text-muted">
          <RecurrenceRuleAsText rrule={ruleObj} />
        </small>
      </div>
    </div>
  )
}

const getByDayValue = recurrenceRule => {
  const match = recurrenceRule.match(/BYDAY=([^;]+)/)
  return match ? match[1] : null
}

const validateForm = values => {
  let errors = {}

  if (!values.rule) {
    errors.rule = 'Required'
  }

  return errors
}

export default function ModalContent() {
  const { rruleValue: recurrenceRule, setFieldValue: setSharedFieldValue } =
    useDeliveryFormFormikContext()

  const { t } = useTranslation()

  const dispatch = useDispatch()

  const defaultRecurrenceRule =
    'FREQ=WEEKLY;BYDAY=' + moment().locale('en').format('dd').toUpperCase()

  const initialValues = {
    rule: recurrenceRule ?? defaultRecurrenceRule,
  }

  return (
    <div data-testid="recurrence__modal__content">
      <div className="modal-header">
        <button
          type="button"
          className="close"
          onClick={() => dispatch(closeRecurrenceModal())}
          aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
        <h4 className="modal-title">
          {t('ADMIN_DASHBOARD_RECURRENCE_RULE_MODAL_TITLE')}
        </h4>
      </div>
      <Formik
        initialValues={initialValues}
        validate={validateForm}
        onSubmit={values => {
          // If no days are selected, consider it as no recurrence
          const rrule = getByDayValue(values.rule) ? values.rule : null
          setSharedFieldValue('rrule', rrule)
          dispatch(closeRecurrenceModal())
        }}
        validateOnBlur={true}
        validateOnChange={false}>
        {({ values, errors, handleSubmit, setFieldValue }) => (
          <div>
            <div className="p-4 border-bottom">
              <RecurrenceEditor
                recurrence={values.rule}
                onChange={newOpts => {
                  const cleanOpts = _.pick(newOpts, ['freq', 'byweekday'])
                  setFieldValue('rule', RRule.optionsToString(cleanOpts))
                }}
              />
              {errors.rule && (
                <div className="text-danger">
                  {t('RECURRENCE_RULE_DAYS_REQUIRED')}
                </div>
              )}
            </div>
            <div
              className={classNames({
                'd-flex': true,
                'p-4': true,
                'justify-content-end': true,
              })}>
              <span data-testid="save">
                <Button type="primary" size="large" onClick={handleSubmit}>
                  {t('ADMIN_DASHBOARD_TASK_FORM_SAVE')}
                </Button>
              </span>
            </div>
          </div>
        )}
      </Formik>
    </div>
  )
}
