import './AddressBook.scss'
import React, { useState } from 'react'
import { Input, Select, Checkbox, Button } from 'antd'
import AddressAutosuggest from '../components/AddressAutosuggest'
import Modal from 'react-modal'
import { useTranslation } from 'react-i18next'
import { useFormikContext, Field } from 'formik'

export default function AddressBook({ index, addresses }) {
  const { t } = useTranslation()
  const { values, setFieldValue } = useFormikContext()
  const toBeModified = values.tasks[index].toBeModified

  const [isModalOpen, setModalOpen] = useState(false)
  const [alreadyAskedForModification, setAlreadyAskedForModification] =
    useState(false)
  const [selectValue, setSelectValue] = useState(null)

  const handleModifyAddress = () => {
    if (
      values.tasks[index].address['@id'] &&
      !toBeModified &&
      !alreadyAskedForModification
    ) {
      setModalOpen(true)
    }
  }

  const handleAddressSelected = value => {
    const selectedAddress = addresses.find(
      address => address.streetAddress === value,
    )
    setFieldValue(`tasks[${index}].address`, {
      ...selectedAddress,
      streetAddress: selectedAddress.streetAddress || '',
      name: selectedAddress.name || '',
      telephone: selectedAddress.telephone || '',
      contactName: selectedAddress.contactName || '',
    })
    setSelectValue(value)
  }

  return (
    <>
      <div className="row">
        <div className="col-md-12 mb-3">
          <Select
            style={{ width: '100%' }}
            showSearch
            placeholder="Rechercher une adresse enregistrée"
            value={selectValue}
            optionFilterProp="label"
            onChange={value => {
              handleAddressSelected(value)
              setSelectValue(value)
            }}
            filterOption={(input, option) =>
              option.label.toLowerCase().includes(input.toLowerCase())
            }
            options={addresses.map(address => ({
              value: address.streetAddress,
              label: address.streetAddress,
              key: address['@id'],
              id: address['@id'],
            }))}
          />
        </div>
      </div>
      <div className="row mb-3">
        <div className="col-md-4">
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
                placeholder="Nom"
              />
            )}
          </Field>
        </div>
        <div className="col-md-4">
          <Field name={`tasks[${index}].address.telephone`}>
            {({ field, form }) => (
              <Input
                {...field}
                value={form.values.tasks[index].address.telephone}
                onChange={e => {
                  handleModifyAddress()
                  form.setFieldValue(
                    `tasks[${index}].address.telephone`,
                    e.target.value,
                  )
                }}
                placeholder="Téléphone"
              />
            )}
          </Field>
        </div>
        <div className="col-md-4">
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
                placeholder="Contact"
              />
            )}
          </Field>
        </div>
        <div className="col-md-12">
          <AddressAutosuggest
            address={values.tasks[index].address || ''}
            addresses={addresses}
            required={true}
            reportValidity={true}
            preciseOnly={true}
            onAddressSelected={(value, address) => {
              setFieldValue(`tasks[${index}].address`, {
                ...address,
                streetAddress: address.streetAddress || '',
                name: address.name || '',
                telephone: address.telephone || '',
                contactName: address.contactName || '',
              })
              setSelectValue(null)
            }}
            onClear={() => {
              setFieldValue(`tasks[${index}].address`, {
                streetAddress: '',
                name: '',
                contactName: '',
                telephone: '',
              })
              setSelectValue(null)
            }}
          />

          <Field name={`tasks[${index}].toBeRemembered`}>
            {({ field }) => (
              <Checkbox
                {...field}
                checked={field.value}
                onChange={e =>
                  setFieldValue(
                    `tasks[${index}].toBeRemembered`,
                    e.target.checked,
                  )
                }>
                Se souvenir de cette adresse
              </Checkbox>
            )}
          </Field>
        </div>
      </div>

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
          {values.tasks[index].address.name} -{' '}
          {values.tasks[index].address.streetAddress}
        </h4>
        <p>{t('ADDRESS_BOOK_PROP_CHANGED_DISCLAIMER')}</p>
        <div className="d-flex justify-content-center">
          <Button
            className="mr-4"
            onClick={() => {
              setFieldValue(`tasks[${index}].toBeModified`, true)
              setModalOpen(false)
            }}>
            {t('ADDRESS_BOOK_PROP_CHANGED_UPDATE')}
          </Button>
          <Button
            type="primary"
            onClick={() => {
              setModalOpen(false)
            }}>
            {t('ADDRESS_BOOK_PROP_CHANGED_ONLY_ONCE')}
          </Button>
        </div>
      </Modal>
    </>
  )
}
