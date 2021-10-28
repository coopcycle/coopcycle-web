import React, { useState, useRef, useEffect } from 'react'
import { render } from 'react-dom'
import _ from 'lodash'
import { Popover, Form, Input, Button } from 'antd'
import { UserOutlined, PhoneOutlined, StarOutlined } from '@ant-design/icons'
import { useTranslation } from 'react-i18next'
import classNames from 'classnames'
import { parsePhoneNumberFromString } from 'libphonenumber-js'

import AddressAutosuggest from '../components/AddressAutosuggest'
import { getCountry } from '../i18n'

const AddressPopoverIcon = ({ prop }) => {
  switch (prop) {
    case 'name':
      return <StarOutlined />
    case 'telephone':
      return <PhoneOutlined />
    case 'contactName':
      return <UserOutlined />
  }
}

const transKeys = {
  name: 'ADMIN_DASHBOARD_TASK_FORM_ADDRESS_NAME',
  telephone: 'ADMIN_DASHBOARD_TASK_FORM_ADDRESS_TELEPHONE',
  contactName: 'ADMIN_DASHBOARD_TASK_FORM_ADDRESS_CONTACT_NAME',
}

function getFormattedValue(prop, value) {
  if (prop === 'telephone' && typeof value === 'string') {
    const phoneNumber = parsePhoneNumberFromString(value, (getCountry() || 'fr').toUpperCase())

    return phoneNumber ? phoneNumber.formatNational() : value
  }

  return value
}

const AddressPopover = ({ address, prop, onChange, id, name, required }) => {

  const inputRef = useRef(null)
  const { t } = useTranslation()
  const [ visible, setVisible ] = useState(false)

  if (!address) {

    return null
  }

  const [ form ] = Form.useForm()

  const onFinish = (values) => {
    const value = values[prop]
    setVisible(false)
    onChange(value)
  }

  useEffect(() => {
    if (visible) {
      inputRef.current && inputRef.current.focus({
        cursor: _.isEmpty(value) ? 'start' : 'end',
      })
    }
  }, [ visible ]);

  const value = address[prop]

  return (
    <Popover
      trigger="click"
      placement="bottom"
      visible={ visible }
      onVisibleChange={ visible => setVisible(visible) }
      content={
        <Form form={ form } name="horizontal_login" layout="inline" onFinish={ onFinish }
          initialValues={{ [ prop ]: getFormattedValue(prop, value) }}>
          <Form.Item
            name={ prop }
            rules={[{ required: true, message: 'Please input your username!' }]}
          >
            <Input
              prefix={<AddressPopoverIcon prop={ prop } />}
              placeholder={ prop === 'telephone' ? '' : t(`${transKeys[prop]}_PLACEHOLDER`) }
              ref={ inputRef } />
          </Form.Item>
          <Form.Item>
            <Button
              type="primary"
              htmlType="submit"
            >
              { t('ADMIN_DASHBOARD_TASK_FORM_SAVE') }
            </Button>
          </Form.Item>
        </Form> }
    >
      <span style={{ position: 'relative', display: 'inline-block' }}>
        <button
          type="button"
          className={ classNames({
            'border': true,
            'rounded-pill': true,
            'py-2': true,
            'px-4': true,
            'mr-2': true,
            'mb-2': true,
            'border-primary': !_.isEmpty(value),
          }) }>
          <span className="mr-2"><AddressPopoverIcon prop={ prop } /></span>
          <span>{ !_.isEmpty(value) ? getFormattedValue(prop, value) : t(`${transKeys[prop]}_LABEL`) }</span>
        </button>
        {/* https://stackoverflow.com/questions/50917016/make-hidden-field-required */}
        <input type="text"
          style={{ opacity: 0, width: 0, position: 'absolute', left: '50%', top: 0, bottom: 0, pointerEvents: 'none' }}
          id={ id }
          name={ name }
          defaultValue={ value }
          required={ required } />
      </span>
    </Popover>
  )
}

const AddressBook = ({
  addresses,
  initialAddress,
  onAddressSelected,
  onClear,
  details,
  ...otherProps
}) => {

  const [ address, setAddress ] = useState(initialAddress)

  const onAddressPropChange = (address, prop, value) => {
    const newAddress = {
      ...address,
      [prop]: value
    }
    setAddress(newAddress)
    onAddressSelected(newAddress.streetAddress, newAddress)
  }

  return (
    <div>
      <div>
        <AddressAutosuggest
          addresses={ addresses }
          address={ address }
          required={ true }
          reportValidity={ true }
          preciseOnly={ true }
          onAddressSelected={ (value, address) => {
            setAddress(address)
            onAddressSelected(value, address)
          }}
          onClear={ () => {
            setAddress('')
            onClear()
          }}
          { ...otherProps } />
      </div>
      { address &&
      <div className="mt-4 mb-2">
        { details.map(item => (
          <AddressPopover
            key={ item.prop }
            address={ address }
            onChange={ value => onAddressPropChange(address, item.prop, value) }
            { ...item } />
        )) }
      </div> }
    </div>
  )
}

function getInputProps(input, prop) {

  const id = input.id
  const name = input.name
  const initialValue = input.value
  const required = input.hasAttribute('required')

  input.remove()

  return {
    prop,
    id,
    name,
    initialValue,
    required,
  }
}

export default function(el, options) {

  const {
    existingAddressControl,
    newAddressControl,
    isNewAddressControl
  } = options

  const addresses = []
  Array.from(existingAddressControl.options).forEach(option => {
    if (option.dataset.address) {
      addresses.push(JSON.parse(option.dataset.address))
    }
  })

  let autosuggestProps = {}

  // Replace the existing address dropdown by a hidden input with the same name & value
  const existingAddressControlHidden = document.createElement('input')

  const existingAddressControlName = existingAddressControl.name
  const existingAddressControlValue = existingAddressControl.value
  const existingAddressControlSelected = existingAddressControl.options[existingAddressControl.selectedIndex]

  existingAddressControlHidden.setAttribute('type', 'hidden')
  existingAddressControlHidden.setAttribute('name', existingAddressControlName)
  existingAddressControlHidden.setAttribute('value', existingAddressControlValue)

  existingAddressControl.remove()
  el.appendChild(existingAddressControlHidden)

  // Replace the new address text field by a hidden input with the same name & value
  const newAddressControlHidden = document.createElement('input')

  const newAddressControlName = newAddressControl.name
  const newAddressControlValue = newAddressControl.value
  const newAddressControlId = newAddressControl.id

  if (newAddressControl.hasAttribute('placeholder')) {
    autosuggestProps = {
      ...autosuggestProps,
      placeholder: newAddressControl.getAttribute('placeholder')
    }
  }

  newAddressControlHidden.setAttribute('type', 'hidden')
  newAddressControlHidden.setAttribute('name', newAddressControlName)
  newAddressControlHidden.setAttribute('value', newAddressControlValue)
  newAddressControlHidden.setAttribute('id', newAddressControlId)

  newAddressControl.remove()
  el.appendChild(newAddressControlHidden)

  // Replace the new address checkbox by a hidden input with the same name & value
  const isNewAddressControlHidden = document.createElement('input')

  const isNewAddressControlName = isNewAddressControl.name
  const isNewAddressControlValue = isNewAddressControl.value
  const isNewAddressControlId = isNewAddressControl.id

  isNewAddressControlHidden.setAttribute('type', 'hidden')
  isNewAddressControlHidden.setAttribute('name', isNewAddressControlName)
  isNewAddressControlHidden.setAttribute('value', isNewAddressControlValue)
  isNewAddressControlHidden.setAttribute('id', isNewAddressControlId)

  isNewAddressControl.closest('.checkbox').remove()
  if (isNewAddressControl.checked) {
    el.appendChild(isNewAddressControlHidden)
  }

  // Callback with initial data
  let address

  if (existingAddressControlSelected.dataset.address) {
    address = JSON.parse(existingAddressControlSelected.dataset.address)
    if (options.onReady && typeof options.onReady === 'function') {
      options.onReady(address)
    }
  }

  if (isNewAddressControl.checked && newAddressControl.value) {
    address = {
      streetAddress: newAddressControl.value,
      postalCode: el.querySelector('[data-address-prop="postalCode"]').value,
      addressLocality: el.querySelector('[data-address-prop="addressLocality"]').value,
      latitude: el.querySelector('[data-address-prop="latitude"]').value,
      longitude: el.querySelector('[data-address-prop="longitude"]').value,
      geo: {
        latitude: el.querySelector('[data-address-prop="latitude"]').value,
        longitude: el.querySelector('[data-address-prop="longitude"]').value,
      }
    }
    if (options.onReady && typeof options.onReady === 'function') {
      options.onReady(address)
    }
  }

  const details = []

  if (options.nameControl) {
    details.push(getInputProps(options.nameControl, 'name'))
  }
  if (options.telephoneControl) {
    details.push(getInputProps(options.telephoneControl, 'telephone'))
  }
  if (options.contactNameControl) {
    details.push(getInputProps(options.contactNameControl, 'contactName'))
  }

  const reactContainer = document.createElement('div')

  el.prepend(reactContainer)

  render(
    <AddressBook
      addresses={ addresses }
      initialAddress={ address }
      details={ details }
      onAddressSelected={ (value, address) => {

        if (address['@id']) {
          existingAddressControlHidden.value = address['@id']
          isNewAddressControlHidden.remove()
        } else {
          newAddressControlHidden.value = address.streetAddress
          el.querySelector('[data-address-prop="postalCode"]').value = address.postalCode
          el.querySelector('[data-address-prop="addressLocality"]').value = address.addressLocality
          el.querySelector('[data-address-prop="latitude"]').value = address.latitude
          el.querySelector('[data-address-prop="longitude"]').value = address.longitude

          if (!document.documentElement.contains(isNewAddressControlHidden)) {
            el.appendChild(isNewAddressControlHidden)
          }
        }

        if (options.onChange && typeof options.onChange === 'function') {
          options.onChange(address)
        }

      } }
      onClear={ () => {
        if (options.onClear && typeof options.onClear === 'function') {
          options.onClear()
        }
      } }
      { ...autosuggestProps } />,
    reactContainer
  )
}
