import React, { useState } from 'react'
import { connect } from 'react-redux'
import { RRule, rrulestr } from 'rrule'
import _ from 'lodash'
import Select from 'react-select'
import { Button, Checkbox, Radio, TimePicker, Input, Popover, Alert, InputNumber, Select as AntSelect, Tag } from 'antd'
import { PlusOutlined, ThunderboltOutlined, UserOutlined, PhoneOutlined, DeleteOutlined, CodeSandboxOutlined } from '@ant-design/icons'
import moment from 'moment'
import hash from 'object-hash'
import { Formik } from 'formik'
import { useTranslation } from 'react-i18next'
import { parsePhoneNumberFromString, AsYouType, isValidPhoneNumber } from 'libphonenumber-js'
import classNames from 'classnames'
import Popconfirm from 'antd/lib/popconfirm'

import { getCountry } from '../../i18n'
import AddressAutosuggest from '../../components/AddressAutosuggest'
import TimeRange from '../../utils/TimeRange'
import { timePickerProps } from '../../utils/antd'
import { recurrenceTemplateToArray } from '../redux/utils'
import { saveRecurrenceRule, createTasksFromRecurrenceRule, deleteRecurrenceRule, closeRecurrenceRuleModal } from '../redux/actions'
import { selectSelectedDate } from '../../coopcycle-frontend-js/logistics/redux'
import RecurrenceRuleAsText from './RecurrenceRuleAsText'

import { phoneNumberExample } from '../utils'
import {DragDropContext, Draggable, Droppable} from "@hello-pangea/dnd";
import ReactDOM from "react-dom";

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

const storesAsOptions = stores => stores.map(s => ({ label: s.name, value: s['@id'], store: s }))

const country = getCountry().toUpperCase()
const asYouTypeFormatter = new AsYouType(country)

const MoreOptions = ({ item, packages, onChange }) => {

  const { t } = useTranslation()

  const phoneNumber = parsePhoneNumberFromString((item.address && item.address.telephone) || '', country)
  const [ telephoneValue, setTelephoneValue ] = useState(phoneNumber ? phoneNumber.formatNational() : '')
  const [ weightValue, setWeightValue ] = useState(item.weight ? item.weight / 1000 : 0)

  const [ contactName, setContactName ] = useState(item.address && item.address.contactName)
  const [ taskComments, setTaskComments ] = useState(item.comments)
  const [ packagesValue, setPackagesValue ] = useState(item.packages ? item.packages : [])

  const packagesOptions = packages.map(p => ({
    label: p.name,
    value: p.name
  }))

  return (
    <Popover
      placement="rightTop"
      style={{ width: '50%' }}
      content={(
        <React.Fragment>
          <div className="mb-3">
            <Input
              placeholder={ t('ADMIN_DASHBOARD_TASK_FORM_ADDRESS_CONTACT_NAME_LABEL') }
              prefix={ <UserOutlined /> }
              value={ contactName }
              onChange={ (e) => setContactName(e.target.value) } />
          </div>
          <div className="mb-3">
            <Input
              type="tel"
              placeholder={ phoneNumberExample }
              prefix={ <PhoneOutlined /> }
              value={ telephoneValue }
              onChange={ (e) => setTelephoneValue(
                asYouTypeFormatter.reset().input(e.target.value)
              )} />
          </div>
          <div className="mb-3">
            <Input
              type="number"
              placeholder={ t('RULE_PICKER_LINE_WEIGHT') }
              prefix={ <CodeSandboxOutlined /> }
              min="0"
              value={ weightValue ? weightValue : null }
              onChange={ (e) => {
                setWeightValue(
                  e.target.value
                )}
              } />
          </div>
          { (item.type === 'DROPOFF' && packages.length) > 0 && (
          <div className="mb-3">
            <AntSelect
              mode="tags"
              allowClear
              style={{ width: '100%' }}
              placeholder={ t('ADMIN_DASHBOARD_PACKAGES') }
              defaultValue={ packagesValue.map(p => p.type) }
              onChange={ (selectedValues) => {
                setPackagesValue(selectedValues.map(v => ({
                  type: v,
                  quantity: _.find(packagesValue, p => p.type === v)?.quantity || 1
                })))
              }}
              options={ packagesOptions }
              tagRender={ (props) => {

                const { label, value, closable, onClose } = props;

                const onPreventMouseDown = (e) => {
                  e.preventDefault();
                  e.stopPropagation();
                }

                let defaultValue = 1
                const idx = _.findIndex(packagesValue, p => p.type === value)
                if (-1 !== idx) {
                  defaultValue = packagesValue[idx].quantity
                }

                return (
                  <Tag
                    onMouseDown={ onPreventMouseDown }
                    closable={ closable}
                    onClose={ onClose }
                  >
                    <InputNumber
                      size="small"
                      style={{ width: 50 }}
                      min={ 1 }
                      defaultValue={ defaultValue }
                      onChange={ quantity => {
                        const newPackagesValue = packagesValue.slice(0)
                        const pkgIndex = _.findIndex(newPackagesValue, p => p.type === value)
                        if (-1 !== pkgIndex) {
                          newPackagesValue.splice(pkgIndex, 1, {
                            ...newPackagesValue[pkgIndex],
                            quantity
                          })
                        }
                        setPackagesValue(newPackagesValue)
                      }} /> × { label }
                  </Tag>
                );
              }} />
          </div>
          )}
          <div>
            <Input.TextArea
              placeholder={ t('ADMIN_DASHBOARD_TASK_FORM_COMMENTS_PLACEHOLDER') }
              autoSize={{ minRows: 3 }}
              value={ taskComments }
              onChange={ e => setTaskComments(e.target.value) } />
          </div>
        </React.Fragment>
      )}
      title={ t('ADMIN_DASHBOARD_TASK_FORM_ADDRESS_MORE_OPTIONS') }
      trigger="click"
      onOpenChange={ visible => {
        if (!visible) {

          let values = {
            contactName,
          }

          const phoneNumber = isValidPhoneNumber(telephoneValue, country) &&
            parsePhoneNumberFromString(telephoneValue, country)

          if (phoneNumber) {
            values = {
              ...values,
              telephone: phoneNumber.format('E.164')
            }
          }

          if (!_.isEmpty(taskComments)) {
            values = {
              ...values,
              comments: taskComments
            }
          }

          if (weightValue) {
            values = {
              ...values,
              weight: weightValue * 1000
            }
          }

          if (packagesValue) {
            values = {
              ...values,
              packages: packagesValue
            }
          }

          onChange(values)
        }
      }}
    >
      <a className="text-muted" href="#">
        <small>
          <i className="fa fa-plus mr-2"></i>
          <span>{ t('ADMIN_DASHBOARD_TASK_FORM_ADDRESS_MORE_OPTIONS') }</span>
        </small>
      </a>
    </Popover>
  )
}

const TemplateItem =({ item, packages, setFieldValues, onClickRemove, errors, ...props}) => {

  const provided = props.provided
  const snapshot = props.snapshot
  const usePortal = snapshot.isDragging

  const child = (
    <li className="mb-4" ref={provided.innerRef}
        {...provided.draggableProps}>
      <span className="d-flex justify-content-between align-items-center mb-2">
      <i {...provided.dragHandleProps} className="fa fa-bars"></i>
        <span className="mr-2 ml-3">
          <Radio.Group
            defaultValue={ item.type }
            size="medium"
            onChange={ (e) => setFieldValues(item, { type: e.target.value }) }>
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
              const cleanAddress = _.pick(address, ['@id', 'streetAddress', 'postalCode'])
              const mergedAddress = { ...item.address, ...cleanAddress }
              setFieldValues(item, { address: mergedAddress })
            }}
            containerProps={{ style: { marginBottom: 0, marginRight: '0.5rem' } }}
            attachToBody />
        </span>
        <span>
          <TimePicker.RangePicker
            { ...timePickerProps }
            value={ [ moment(item.after, 'HH:mm'), moment(item.before, 'HH:mm') ] }
            onChange={ (value) => {
              setFieldValues(item, {
                after: value[0].format('HH:mm'),
                before: value[1].format('HH:mm')
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
      </span>
      <MoreOptions
        item={ item }
        packages={ packages }
        onChange={ (values) => {
          const newAddress = {
            ...item.address,
            contactName: values.contactName,
            telephone: values.telephone,
          }

          let newValues = {
            address: newAddress
          }

          if (values.comments) {
            newValues = {
              ...newValues,
              comments: values.comments,
            }
          }
          if (values.weight){
            newValues = {
              ...newValues,
              weight: values.weight
            }
          }
          if (values.packages) {
            newValues = {
              ...newValues,
              packages: values.packages
            }
          }

          setFieldValues(item, newValues)
        }} />
    </li>
  )

  // https://github.com/atlassian/@hello-pangea/dnd/blob/master/stories/src/portal/portal-app.jsx
  if (!usePortal) {
    return child
  }

  return ReactDOM.createPortal(child, document.body)
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
        <small className="text-muted"><RecurrenceRuleAsText rrule={ ruleObj } /></small>
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
          address: 'Please select an address'
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

const ModalContent = ({ recurrenceRule, saveRecurrenceRule, closeRecurrenceRuleModal, createTasksFromRecurrenceRule, stores, loading, date, deleteRecurrenceRule, error }) => {

  const { t } = useTranslation()

  const [ packages, setPackages ] = useState(
    recurrenceRule ? (_.find(stores, s => s['@id'] === recurrenceRule.store)?.packages || []) : []
  )

  const storesOptions = storesAsOptions(stores)

  const defaultRecurrence =
    'FREQ=WEEKLY;BYDAY=' + moment(date).locale('en').format('dd').toUpperCase()

  const initialValues = {
    store: recurrenceRule ? recurrenceRule.store : null,
    recurrence: recurrenceRule ? recurrenceRule.rule : defaultRecurrence,
    items: recurrenceRule ?
      recurrenceTemplateToArray(recurrenceRule.template) : [ { ...defaultTask } ],
    name: recurrenceRule?.name,
  }

  const isSaved = recurrenceRule && Object.prototype.hasOwnProperty.call(recurrenceRule, '@id')

  const reorder = (list, oldIndex, newIndex) => {
      const result = Array.from(list);
      const [removed] = result.splice(oldIndex, 1);
      result.splice(newIndex, 0, removed);
      return result;
    }

  return (
    <div>
      <div className="modal-header">
          <button type="button" className="close" onClick={ () => closeRecurrenceRuleModal() } aria-label="Close"><span aria-hidden="true">&times;</span></button>
          <h4 className="modal-title">{ t('ADMIN_DASHBOARD_RECURRENCE_RULE_MODAL_TITLE') }</h4>
      </div>
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
            },
            name: values.name,
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
            <div className="p-4 border-bottom" >
                <input className="form-control"
                      placeholder={ t('ADMIN_RECURRENCE_RULE_NAME_PLACEHOLDER') }
                      value={ values.name }
                      onChange={ e => setFieldValue('name', e.target.value) } />
            </div>
            <div className="p-4 border-bottom">
              <Select
                defaultValue={ _.find(storesOptions, o => o.value === values.store) }
                options={ storesOptions }
                onChange={ ({ value, store }) => {
                  setFieldValue('store', value)
                  setPackages(store.packages)
                }}
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
            <div className="px-4 pt-4 border-bottom" style={{ maxHeight: '50vh', overflow: 'auto' }}>
            <DragDropContext onDragEnd={ e => {
              if (!e.destination)
              { return }
              setFieldValue('items', reorder(values.items, e.source.index, e.destination.index))
            } }>
              <Droppable direction="vertical" droppableId="droppable">
                {(provided) => (
                  <ol className="list-unstyled" ref={provided.innerRef}
                      {...provided.droppableProps}>
                    { values.items.map((item, index) => (
                        <Draggable key={index} draggableId={'pos:' + index} index={index}>
                          {(provided, snapshot) => <TemplateItem
                              provided={provided}
                              snapshot={snapshot}
                              key={ `${index}-${hash(item)}` }
                              item={ item }
                              packages={ packages }
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
                              errors={ (errors && errors.items && errors.items[index]) || {} } />}
                        </Draggable>
                    )) }
                    {provided.placeholder}
                  </ol>)}
              </Droppable>
            </DragDropContext>
            </div>
            <div className="p-4 border-bottom">
              <Button icon={ <PlusOutlined /> } onClick={ () => {
                const newItems = values.items.slice(0)
                newItems.push({ ...defaultTask })
                setFieldValue('items', newItems)
              }}>{ t('ADMIN_DASHBOARD_ADD') }</Button>
            </div>
            { !_.isEmpty(error) &&
              <Alert message={ error } type="error" showIcon />
            }
            <div className={ classNames({
              'd-flex': true,
              'p-4': true,
              'justify-content-end': !isSaved,
              'justify-content-between': isSaved
            })}>
              { isSaved &&
                <Popconfirm
                  placement="right"
                  title={ t('CONFIRM_DELETE') }
                  onConfirm={ () => deleteRecurrenceRule(recurrenceRule) }
                  okText={ t('CROPPIE_CONFIRM') }
                  cancelText={ t('CROPPIE_CANCEL') }
                  >
                  <Button type="danger" size="large" icon={ <DeleteOutlined /> }
                    disabled={ loading }>
                    { t('ADMIN_DASHBOARD_DELETE') }
                  </Button>
                </Popconfirm>
              }
              <span>
                { isSaved &&
                  <span className="mr-4">
                    <Button size="large" icon={ <ThunderboltOutlined /> }
                      onClick={ () => {
                        createTasksFromRecurrenceRule(recurrenceRule)
                      }}> { t('ADMIN_DASHBOARD_TASK_FORM_CREATE') }
                      </Button>
                  </span>
                }
                <Button type="primary" size="large" onClick={ handleSubmit } loading={ loading }>
                  { t('ADMIN_DASHBOARD_TASK_FORM_SAVE') }
                </Button>
              </span>
            </div>
          </div>
        )}
      </Formik>
    </div>
  )
}

function mapStateToProps(state) {

  return {
    date: selectSelectedDate(state),
    recurrenceRule: state.currentRecurrenceRule,
    stores: state.config.stores,
    loading: state.recurrenceRulesLoading,
    error: state.recurrenceRulesErrorMessage,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    saveRecurrenceRule: (recurrenceRule) => dispatch(saveRecurrenceRule(recurrenceRule)),
    createTasksFromRecurrenceRule: (recurrenceRule) => dispatch(createTasksFromRecurrenceRule(recurrenceRule)),
    deleteRecurrenceRule: (recurrenceRule) => dispatch(deleteRecurrenceRule(recurrenceRule)),
    closeRecurrenceRuleModal: () => dispatch(closeRecurrenceRuleModal()),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(ModalContent)
