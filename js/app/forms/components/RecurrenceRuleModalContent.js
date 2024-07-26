import React from 'react'
import { RRule, rrulestr } from 'rrule'
import _ from 'lodash'
import Select from 'react-select'
import { Button, Checkbox } from 'antd'
import { DeleteOutlined } from '@ant-design/icons'
import moment from 'moment'
import { Formik } from 'formik'
import { useTranslation } from 'react-i18next'
import classNames from 'classnames'
import Popconfirm from 'antd/lib/popconfirm'

import TimeRange from '../../utils/TimeRange'
import RecurrenceRuleAsText from './RecurrenceRuleAsText'
import { useDispatch, useSelector } from 'react-redux'
import {
  closeRecurrenceModal,
  createRecurrenceRule,
  deleteRecurrenceRule,
  selectRecurrenceRule,
  updateRecurrenceRule,
} from '../redux/recurrenceSlice'

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

  if (!values.rule || !getByDayValue(values.rule)) {
    errors.rule = 'Required'
  }

  return errors
}

export default function ModalContent() {
  const recurrenceRule = useSelector(selectRecurrenceRule)

  const { t } = useTranslation()

  const dispatch = useDispatch()

  const defaultRecurrenceRule =
    'FREQ=WEEKLY;BYDAY=' + moment().locale('en').format('dd').toUpperCase()

  const initialValues = {
    rule: recurrenceRule ?? defaultRecurrenceRule,
  }

  const isSaved = Boolean(recurrenceRule)

  return (
    <div>
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
          if (isSaved) {
            dispatch(
              updateRecurrenceRule(values.rule),
            )
          } else {
            dispatch(
              createRecurrenceRule(values.rule),
            )
          }
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
                'justify-content-end': !isSaved,
                'justify-content-between': isSaved,
              })}>
              {isSaved && (
                <Popconfirm
                  placement="right"
                  title={t('CONFIRM_DELETE')}
                  onConfirm={() => {
                    dispatch(deleteRecurrenceRule(recurrenceRule))
                    dispatch(closeRecurrenceModal())
                  }}
                  okText={t('CROPPIE_CONFIRM')}
                  cancelText={t('CROPPIE_CANCEL')}>
                  <Button type="danger" size="large" icon={<DeleteOutlined />}>
                    {t('ADMIN_DASHBOARD_DELETE')}
                  </Button>
                </Popconfirm>
              )}
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
