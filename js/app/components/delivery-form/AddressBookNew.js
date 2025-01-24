import './AddressBookNew.scss'
import React, { useEffect, useState } from 'react'
import { Input, Select, Checkbox, Button } from 'antd'
import AddressAutosuggest from '../AddressAutosuggest'
import Modal from 'react-modal'
import { useTranslation } from 'react-i18next'
import { useFormikContext, Field } from 'formik'
import { getCountry } from '../../i18n'
import { parsePhoneNumberFromString } from 'libphonenumber-js'

function getFormattedValue(value) {
  if (typeof value === 'string') {
    const phoneNumber = parsePhoneNumberFromString(
      value,
      (getCountry() || 'fr').toUpperCase(),
    )
    return phoneNumber ? phoneNumber.formatNational() : value
  }
  return value
}

function getUnformattedValue(value) {
  if (typeof value === 'string') {
    const phoneNumber = parsePhoneNumberFromString(
      value,
      (getCountry() || 'fr').toUpperCase(),
    )

    return phoneNumber ? phoneNumber.format('E.164') : value
  }

  // If value is null or undefined, we make sure to return an empty string,
  // because React treats value={ undefined | null } as an uncontrolled component
  // https://stackoverflow.com/questions/49969577/warning-when-changing-controlled-input-value-in-react
  return value ?? ''
}

export default function AddressBook({ index, addresses, storeDeliveryInfos }) {
  const { t } = useTranslation()
  const { values, setFieldValue, errors } = useFormikContext()
  const updateInStoreAddresses = values.tasks[index].updateInStoreAddresses

  const [isModalOpen, setModalOpen] = useState(false)
  const [alreadyAskedForModification, setAlreadyAskedForModification] =
    useState(false)
  const [selectValue, setSelectValue] = useState(null)

  /* To handle the case where the user picked a remembered address in select but change contactName, name or telephone value */
  const handleModifyAddress = () => {
    if (
      values.tasks[index].address['@id'] &&
      !updateInStoreAddresses &&
      !alreadyAskedForModification
    ) {
      setModalOpen(true)
    }
  }

  /** This one is used by the select. Only if the user picked a remembered address. */

  const handleAddressSelected = value => {
    const selectedAddress = addresses.find(
      address => address.streetAddress === value,
    )
    const formattedTelephone = getFormattedValue(selectedAddress.telephone)

    setFieldValue(`tasks[${index}].address`, {
      ...selectedAddress,
      streetAddress: selectedAddress.streetAddress || '',
      name: selectedAddress.name || '',
      telephone: selectedAddress.telephone || null,
      formattedTelephone,
      contactName: selectedAddress.contactName || '',
    })
    setSelectValue(value)
  }

  useEffect(() => {
    if (storeDeliveryInfos.address && storeDeliveryInfos.prefillPickupAddress) {
      handleAddressSelected(storeDeliveryInfos.address.streetAddress)
    }
  }, [storeDeliveryInfos.address, storeDeliveryInfos.prefillPickupAddress])

  /** The value used by the input is formatedTelephone, as we need to send telephone with international area code
   * We also need to set the value to null if input is empty because React treats it as empty string and it causes validation errors from the back
   */
  const handleTelephone = (e, form) => {
    const formattedTelephone = e.target.value

    if (formattedTelephone === '') {
      form.setFieldValue(`tasks[${index}].address.formattedTelephone`, null)
      form.setFieldValue(`tasks[${index}].address.telephone`, null)
    } else {
      const telephone = getUnformattedValue(formattedTelephone)
      form.setFieldValue(
        `tasks[${index}].address.formattedTelephone`,
        formattedTelephone,
      )
      form.setFieldValue(`tasks[${index}].address.telephone`, telephone)
    }
  }

  /** to reset if the address has to be modified in case the user changes the address selected */

  const resetToBeModified = () => {
    if (updateInStoreAddresses) {
      setFieldValue(`tasks[${index}].updateInStoreAddresses`, false)
    }
    setAlreadyAskedForModification(false)
  }

  return (
    <div className="address-container mb-4">
      <div className="row">
        <div className="col-sm-12 mb-3">
          <Select
            style={{ width: '100%' }}
            showSearch
            placeholder={t('TASK_FORM_SEARCH_SAVED_ADDRESS_BY_NAME')}
            value={selectValue}
            optionFilterProp="label"
            onChange={value => {
              handleAddressSelected(value)
              resetToBeModified()
            }}
            filterOption={(input, option) =>
              option.label.toLowerCase().includes(input.toLowerCase())
            }
            options={addresses.map(address => ({
              value: address.streetAddress,
              label: `${address.name} - ${address.streetAddress}`,
              key: address['@id'],
              id: address['@id'],
            }))}
          />
        </div>
      </div>
      <div className="mb-3 address-infos">
        <div className="address-infos__item">
          <Field name={`tasks[${index}].address.name`}>
            {({ field, form }) => (
              <Input
                {...field}
                value={form.values.tasks[index].address.name}
                onChange={e => {
                  handleModifyAddress()
                  form.setFieldValue(
                    `tasks[${index}].address.name`,
                    e.target.value,
                  )
                }}
                placeholder={t('ADMIN_DASHBOARD_TASK_FORM_ADDRESS_NAME_LABEL')}
              />
            )}
          </Field>
        </div>
        <div className="address-infos__item">
          <Field name={`tasks[${index}].address.formattedTelephone`}>
            {({ field, form }) => (
              <>
                <Input
                  {...field}
                  value={form.values.tasks[index].address.formattedTelephone}
                  onChange={e => {
                    handleModifyAddress()
                    handleTelephone(e, form)
                  }}
                  placeholder={t(
                    'ADMIN_DASHBOARD_TASK_FORM_ADDRESS_TELEPHONE_LABEL',
                  )}
                />
                {errors.tasks?.[index]?.address?.formattedTelephone && (
                  <div className="text-danger">
                    {errors.tasks[index].address.formattedTelephone}
                  </div>
                )}
              </>
            )}
          </Field>
        </div>
        <div className="address-infos__item">
          <Field name={`tasks[${index}].address.contactName`}>
            {({ field, form }) => (
              <Input
                {...field}
                value={form.values.tasks[index].address.contactName}
                onChange={e => {
                  handleModifyAddress()
                  form.setFieldValue(
                    `tasks[${index}].address.contactName`,
                    e.target.value,
                  )
                }}
                placeholder={t('DELIVERY_FORM_CONTACT_PLACEHOLDER')}
              />
            )}
          </Field>
        </div>
      </div>
      <div className="row">
        <div className="col-sm-12">
          <AddressAutosuggest
            address={values.tasks[index].address || ''}
            required={true}
            reportValidity={true}
            preciseOnly={true}
            onAddressSelected={(value, address) => {
              setFieldValue(`tasks[${index}].address`, {
                ...address,
                name: values.tasks[index].address.name || '',
                telephone: values.tasks[index].address.telephone || null,
                formattedTelephone:
                  values.tasks[index].address.formattedTelephone || null,
                contactName: values.tasks[index].address.contactName || '',
              })
              setSelectValue(null)
              resetToBeModified()
            }}
            onClear={() => {
              setFieldValue(`tasks[${index}].address`, {
                streetAddress: '',
                name: values.tasks[index].address.name || '',
                telephone: values.tasks[index].address.telephone || null,
                formattedTelephone:
                  values.tasks[index].address.formattedTelephone || null,
                contactName: values.tasks[index].address.contactName || '',
              })
              setSelectValue(null)
            }}
          />
          {!selectValue && (
            <Field name={`tasks[${index}].saveInStoreAddresses`}>
              {({ field }) => (
                <Checkbox
                  {...field}
                  checked={field.value}
                  onChange={e =>
                    setFieldValue(
                      `tasks[${index}].saveInStoreAddresses`,
                      e.target.checked,
                    )
                  }>
                  {t('DELIVERY_FORM_REMEMBER_ADDRESS')}
                </Checkbox>
              )}
            </Field>
          )}
        </div>
      </div>
      {/* Modal to handle if the user want to change the remembred address just for once or forever */}
      <Modal
        isOpen={isModalOpen}
        onRequestClose={() => {
          setModalOpen(false)
          setAlreadyAskedForModification(true)
        }}
        shouldCloseOnOverlayClick={false}
        contentLabel={t('ADDRESS_BOOK_PROP_CHANGED_DISCLAIMER')}
        overlayClassName="ReactModal__Overlay--addressProp"
        className="ReactModal__Content--addressProp"
        htmlOpenClassName="ReactModal__Html--open"
        bodyOpenClassName="ReactModal__Body--open">
        <h4 className="text-center">
          {values.tasks[index].address.name} -
          {values.tasks[index].address.streetAddress}
        </h4>
        <p>{t('ADDRESS_BOOK_PROP_CHANGED_DISCLAIMER')}</p>
        <div className="d-flex justify-content-center">
          <Button
            className="mr-4"
            onClick={() => {
              setFieldValue(`tasks[${index}].updateInStoreAddresses`, true)
              setModalOpen(false)
            }}>
            {t('ADDRESS_BOOK_PROP_CHANGED_UPDATE')}
          </Button>
          <Button
            type="primary"
            onClick={() => {
              setModalOpen(false)
              setAlreadyAskedForModification(true)
            }}>
            {t('ADDRESS_BOOK_PROP_CHANGED_ONLY_ONCE')}
          </Button>
        </div>
      </Modal>
    </div>
  )
}
