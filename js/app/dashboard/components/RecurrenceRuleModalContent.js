import React from 'react'
import { connect } from 'react-redux'
import { RRule, rrulestr } from 'rrule'
import _ from 'lodash'
import Select from 'react-select'
import { Button, Checkbox, Radio, TimePicker } from 'antd'
import { PlusOutlined, ThunderboltOutlined } from '@ant-design/icons'
import moment from 'moment'
import hash from 'object-hash'
import { Formik } from 'formik'
import { useTranslation } from 'react-i18next'

import AddressAutosuggest from '../../components/AddressAutosuggest'
import TimeRange from '../../utils/TimeRange'
import { timePickerProps } from '../../utils/antd'
import { recurrenceTemplateToArray } from '../redux/utils'
import { saveRecurrenceRule, createTasksFromRecurrenceRule } from '../redux/actions'
import { selectSelectedDate } from '../../coopcycle-frontend-js/dispatch/redux'
import { toTextArgs } from '../utils/rrule'

const freqOptions = [
  { value: RRule.DAILY, label: 'Every day' },
  { value: RRule.WEEKLY, label: 'Every week' }
]

const locale = $('html').attr('lang')
const weekdays = TimeRange.weekdaysShort(locale)

const byDayOptions = weekdays.map(weekday => ({
  label: weekday.name,
  value: RRule[weekday.key.toUpperCase()].weekday,
}))

const storesAsOptions = stores => stores.map(s => ({ label: s.name, value: s['@id'] }))

const TemplateItem = ({ item, setFieldValue, setFieldValues, onClickRemove, errors }) => {

  return (
    <li className="d-flex justify-content-between align-items-center mb-4">
      <span className="mr-2">
        <Radio.Group
          defaultValue={ item.type }
          size="medium"
          onChange={ (e) => setFieldValue(item, 'type', e.target.value) }>
          <Radio.Button value="PICKUP">
            <i className="fa fa-cube"></i>
          </Radio.Button>
          <Radio.Button value="DROPOFF">
            <i className="fa fa-arrow-down"></i>
          </Radio.Button>
        </Radio.Group>
      </span>
      <span
        className={ errors.address ? 'has-error' : '' }
        style={{ flex: 1 }}>
        <AddressAutosuggest
          address={ item.address }
          onAddressSelected={ (value, address) => {
            const cleanAddress = _.pick(address, ['@id', 'streetAddress'])
            setFieldValue(item, 'address', cleanAddress)
          }}
          containerProps={{ style: { marginBottom: 0, marginRight: '0.5rem' } }}
          attachToBody />
      </span>
      <span>
        <TimePicker.RangePicker
          { ...timePickerProps }
          defaultValue={ [ moment(item.after, 'HH:mm'), moment(item.before, 'HH:mm') ] }
          onChange={ (value, text) => {
            setFieldValues(item, {
              after: text[0],
              before: text[1]
            })
          }}
        />
      </span>
      <a href="#" className="ml-2" onClick={ e => {
        e.preventDefault()
        onClickRemove(item)
        }}>
        <i className="fa fa-lg fa-times"></i>
      </a>
    </li>
  )
}

const RecurrenceEditor = ({ recurrence, onChange }) => {

  const ruleObj = rrulestr(recurrence)
  const defaultValue = _.find(freqOptions, option => option.value === ruleObj.options.freq)

  return (
    <div>
      {/* It is not possible to change FREQ atm */}
      <div className="mb-4 d-none">
        <Select
          options={ freqOptions }
          defaultValue={ defaultValue }
          onChange={ option => onChange({ ...ruleObj.options, freq: option.value }) }
          />
      </div>
      <div className="mb-2">
        <Checkbox.Group
          options={ byDayOptions }
          defaultValue={ ruleObj.options.byweekday }
          onChange={ opts => onChange({ ...ruleObj.options, byweekday: opts }) } />
      </div>
      <div>
        <small className="text-muted">{ ruleObj.toText(...toTextArgs()) }</small>
      </div>
    </div>
  )
}

const defaultTask = {
  '@type': 'Task',
  type: 'DROPOFF',
  address: {
    streetAddress: ''
  },
  after: '00:00',
  before: '23:59',
}

const validateForm = values => {

  let errors = {}

  if (!values.store) {
    errors = {
      ...errors,
      store: 'Please select a store'
    }
  }

  if (values.items.length === 0) {
    errors = {
      ...errors,
      items: 'Please add at least one task'
    }
  } else {

    const itemsErrors = values.items.map(item => {
      if (_.isEmpty(item.address.streetAddress)) {
        return {
          address: 'Veuillez sélectionner une addresse'
        }
      }

      return {}
    })

    // Add error prop when there is at least one error
    const firstError = _.find(itemsErrors, e => !_.isEmpty(e.address))
    if (firstError) {
      errors = {
        ...errors,
        items: itemsErrors
      }
    }
  }

  return errors
}

const ModalContent = ({ recurrenceRule, saveRecurrenceRule, createTasksFromRecurrenceRule, stores, loading, date }) => {

  const { t } = useTranslation()

  const storesOptions = storesAsOptions(stores)

  const defaultRecurrence =
    'FREQ=WEEKLY;BYDAY=' + moment(date).locale('en').format('dd').toUpperCase()

  const initialValues = {
    store: recurrenceRule ? recurrenceRule.store : null,
    recurrence: recurrenceRule ? recurrenceRule.rule : defaultRecurrence,
    items: recurrenceRule ?
      recurrenceTemplateToArray(recurrenceRule.template) : [ { ...defaultTask } ],
  }

  return (
    <Formik
      initialValues={ initialValues }
      validate={ validateForm }
      onSubmit={ values => {
        saveRecurrenceRule({
          ...recurrenceRule,
          store: values.store,
          rule: values.recurrence,
          template: {
            '@type': 'hydra:Collection',
            'hydra:member': values.items
          }
        })
      }}
      validateOnBlur={ true }
      validateOnChange={ false }
    >
      {({
        values,
        errors,
        handleSubmit,
        setFieldValue,
      }) => (
        <div>
          <div className="p-4 border-bottom">
            <Select
              defaultValue={ _.find(storesOptions, o => o.value === values.store) }
              options={ storesOptions }
              onChange={ ({ value }) => setFieldValue('store', value) }
              // https://github.com/coopcycle/coopcycle-web/issues/774
              // https://github.com/JedWatson/react-select/issues/3030
              menuPortalTarget={ document.body }
              styles={{
                menuPortal: base => ({ ...base, zIndex: 9 }),
                control: styles => ({
                  ...styles,
                  borderColor: errors.store ? '#DE350B' : styles.borderColor,
                })
              }} />
          </div>
          <div className="p-4 border-bottom">
            <RecurrenceEditor
              recurrence={ values.recurrence }
              onChange={ (newOpts) => {
                const cleanOpts = _.pick(newOpts, ['freq', 'byweekday'])
                setFieldValue('recurrence', RRule.optionsToString(cleanOpts))
              }} />
          </div>
          <div className="px-4 pt-4 border-bottom"
            style={{ maxHeight: '50vh', overflow: 'auto' }}>
            <ol className="list-unstyled">
            { values.items.map((item, index) => (
              <TemplateItem
                key={ `${index}-${hash(item)}` }
                item={ item }
                setFieldValue={ (item, name, value) => {
                  const index = values.items.indexOf(item)
                  if (-1 !== index) {
                    const newItems = values.items.slice(0)
                    newItems.splice(index, 1, { ...item, [name]: value })
                    setFieldValue('items', newItems)
                  }
                }}
                setFieldValues={ (item, fieldValues) => {
                  const index = values.items.indexOf(item)
                  if (-1 !== index) {
                    const newItems = values.items.slice(0)
                    newItems.splice(index, 1, { ...item, ...fieldValues })
                    setFieldValue('items', newItems)
                  }
                }}
                onClickRemove={ item => {
                  const index = values.items.indexOf(item)
                  if (-1 !== index) {
                    const newItems = values.items.slice(0)
                    newItems.splice(index, 1)
                    setFieldValue('items', newItems)
                  }
                }}
                errors={ (errors && errors.items && errors.items[index]) || {} } />
            )) }
            </ol>
          </div>
          <div className="p-4 border-bottom">
            <Button icon={ <PlusOutlined /> } onClick={ () => {
              const newItems = values.items.slice(0)
              newItems.push({ ...defaultTask })
              setFieldValue('items', newItems)
            }}>{ t('ADMIN_DASHBOARD_ADD') }</Button>
          </div>
          <div className="d-flex justify-content-end p-4">
            { (recurrenceRule && Object.prototype.hasOwnProperty.call(recurrenceRule, '@id')) &&
              <span className="mr-4">
                <Button size="large" icon={ <ThunderboltOutlined /> } onClick={ () => {
                  createTasksFromRecurrenceRule(recurrenceRule)
                }}>Create tasks</Button>
              </span>
            }
            <Button type="primary" size="large" onClick={ handleSubmit } loading={ loading }>
              { t('ADMIN_DASHBOARD_TASK_FORM_SAVE') }
            </Button>
          </div>
        </div>
      )}
    </Formik>
  )
}

function mapStateToProps(state) {

  return {
    date: selectSelectedDate(state),
    recurrenceRule: state.currentRecurrenceRule,
    stores: state.stores,
    loading: state.recurrenceRulesLoading,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    saveRecurrenceRule: (recurrenceRule) => dispatch(saveRecurrenceRule(recurrenceRule)),
    createTasksFromRecurrenceRule: (recurrenceRule) => dispatch(createTasksFromRecurrenceRule(recurrenceRule))
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(ModalContent)
