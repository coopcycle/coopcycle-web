import React, { useState, useRef, useEffect } from 'react'
import { render } from 'react-dom'
import _ from 'lodash'
import {  Input, Button } from 'antd'
import { UserOutlined, PhoneOutlined, StarOutlined } from '@ant-design/icons'
import { useTranslation } from 'react-i18next'
import { parsePhoneNumberFromString } from 'libphonenumber-js'
import Modal from 'react-modal'

import AddressAutosuggest from '../components/AddressAutosuggest'
import { getCountry } from '../i18n'

import './AddressBook.scss'
import SavedAddressesBox from './SavedAddressBox'

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

function getUnformattedValue(prop, value) {
  if (prop === 'telephone' && typeof value === 'string') {
    const phoneNumber = parsePhoneNumberFromString(value, (getCountry() || 'fr').toUpperCase())

    return phoneNumber ? phoneNumber.format('E.164') : value
  }

  console.log(value)

  // If value is null or undefined, we make sure to return an empty string,
  // because React treats value={ undefined | null } as an uncontrolled component
  // https://stackoverflow.com/questions/49969577/warning-when-changing-controlled-input-value-in-react
  return value ?? ''
}

const AddressPopover = ({ address, prop, onChange, id, name, required }) => {

  const inputRef = useRef(null)
  const { t } = useTranslation()
  const [ visible, setVisible ] = useState(false)

  // const [ form ] = Form.useForm()

  useEffect(() => {
    if (visible) {
      inputRef.current && inputRef.current.focus({
        cursor: _.isEmpty(value) ? 'start' : 'end',
        // Make sure the page is not scrolled to top when focusing
        // https://github.com/coopcycle/coopcycle-web/issues/3411
        preventScroll: true,
      })
    }
  }, [ visible ]);

  if (!address) {

    return null
  }

  const onFinish = (values) => {
    const value = values[prop]
    setVisible(false)
    onChange(getUnformattedValue(prop, value))
  }

  const value = address[prop]

  const onInputBlur = (event) => {
    let d = {}
    d[prop] = event.target.value
    onFinish(d)
  }

  return (
      <>
        <Input
          style={{ width: '33%' }}
          prefix={<AddressPopoverIcon prop={ prop } />}
          placeholder={ prop === 'telephone' ? '' : t(`${transKeys[prop]}_PLACEHOLDER`) }
          ref={ inputRef }
          onBlur={ onInputBlur }
          defaultValue={ getFormattedValue(prop, value) }
        />
        <input
          type="text"
          tabIndex="-1"
          style={{ opacity: 0, width: 0, position: 'absolute', left: '50%', top: 0, bottom: 0, pointerEvents: 'none' }}
          id={ id }
          name={ name }
          value={ getUnformattedValue(prop, value) }
          onChange={ () => null }
          required={ required } />
    </>
  )
}

const AddressBook = ({
  baseTestId,
  addresses,
  initialAddress,
  onAddressSelected,
  onDuplicateAddress,
  onClear,
  details,
  allowSearchSavedAddresses,
  ...otherProps
}) => {

  const { t } = useTranslation()
  const [ address, setAddress ] = useState(initialAddress)
  const [ isModalOpen, setModalOpen ] = useState(false)

  const onAddressPropChange = (address, prop, value) => {

    const newAddress = {
      ...address,
      [prop]: value
    }
    setAddress(newAddress)
    onAddressSelected(newAddress.streetAddress, newAddress)

    if (address['@id']) {
      const oldValue = address[prop]
      if (!_.isEmpty(oldValue) && oldValue !== value) {
        setModalOpen(true)
      }
    }
  }

  return (
    <div>
       {allowSearchSavedAddresses &&
          <SavedAddressesBox
            addresses={addresses}
            address={address} onSelected={(selected) => {
              setAddress(selected)
              onAddressSelected(selected.streetAddress, selected)
            }}
          />
       }
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
      <div className="my-4 p-2" style={{border: "1px solid grey", borderRadius: '4px'}}>
        <Input.Group compact>
        { _.map(details, item => (
          <AddressPopover
            key={ item.prop }
            baseTestId={ baseTestId }
            address={ address }
            onChange={ value => onAddressPropChange(address, item.prop, value) }
            { ...item } />
        )) }
        {
          address['@id'] ? <span className="text-muted pull-right">From address book</span>: null
        }
        </Input.Group>

      </div> }
      <Modal
        isOpen={ isModalOpen }
        onRequestClose={ () => setModalOpen(false) }
        shouldCloseOnOverlayClick={ false }
        contentLabel={ t('ADDRESS_BOOK_PROP_CHANGED_DISCLAIMER') }
        overlayClassName="ReactModal__Overlay--addressProp"
        className="ReactModal__Content--addressProp"
        htmlOpenClassName="ReactModal__Html--open"
        bodyOpenClassName="ReactModal__Body--open">
        <p>{ t('ADDRESS_BOOK_PROP_CHANGED_DISCLAIMER') }</p>
        <div className="d-flex justify-content-center">
          <Button
            className="mr-4"
            onClick={ () => {
              onDuplicateAddress(false)
              setModalOpen(false)
            }}>{ t('ADDRESS_BOOK_PROP_CHANGED_UPDATE') }</Button>
          <Button type="primary"
            onClick={ () => {
              onDuplicateAddress(true)
              setModalOpen(false)
            }}>{ t('ADDRESS_BOOK_PROP_CHANGED_ONLY_ONCE') }</Button>
        </div>
      </Modal>
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
    isNewAddressControl,
    duplicateAddressControl,
    allowSearchSavedAddresses,
  } = options

  Modal.setAppElement(el)

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

  // Replace the duplicate address checkbox by a hidden input with the same name & value
  let duplicateAddressControlHidden
  if (duplicateAddressControl) {
    duplicateAddressControlHidden = document.createElement('input')

    const duplicateAddressControlName  = duplicateAddressControl.name
    const duplicateAddressControlValue = duplicateAddressControl.value
    const duplicateAddressControlId    = duplicateAddressControl.id

    duplicateAddressControlHidden.setAttribute('type', 'hidden')
    duplicateAddressControlHidden.setAttribute('name',  duplicateAddressControlName)
    duplicateAddressControlHidden.setAttribute('value', duplicateAddressControlValue)
    duplicateAddressControlHidden.setAttribute('id',    duplicateAddressControlId)

    duplicateAddressControl.closest('.checkbox').remove()
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

  let details = {}

  if (options.nameControl) {
    details = {
      ...details,
      name: getInputProps(options.nameControl, 'name'),
    }
  }
  if (options.telephoneControl) {
    details = {
      ...details,
      telephone: getInputProps(options.telephoneControl, 'telephone'),
    }
  }
  if (options.contactNameControl) {
    details = {
      ...details,
      contactName: getInputProps(options.contactNameControl, 'contactName'),
    }
  }

  const reactContainer = document.createElement('div')

  el.append(reactContainer)

  render(
    <AddressBook
      baseTestId={el.id ?? 'AddressBook'}
      allowSearchSavedAddresses={ Boolean(allowSearchSavedAddresses) }
      addresses={ addresses }
      initialAddress={ address }
      details={ details }
      // The onAddressSelected callback is *ALSO* called when
      // an address prop (name, telephone, contactName) is modified
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
      onDuplicateAddress={ (duplicate) => {
        if (duplicateAddressControlHidden) {
          if (duplicate) {
            if (!document.documentElement.contains(duplicateAddressControlHidden)) {
              el.appendChild(duplicateAddressControlHidden)
            }
          } else {
            duplicateAddressControlHidden.remove()
          }
        }
      }}
      { ...autosuggestProps } />,
    reactContainer
  )
}
