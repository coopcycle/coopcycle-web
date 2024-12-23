import './AddressBook.scss'
import React, { useState } from 'react'
import { Input, Select, Checkbox, Button } from 'antd'
import AddressAutosuggest from '../components/AddressAutosuggest'
import Modal from 'react-modal'
import { useTranslation } from 'react-i18next'
// import { getCountry } from '../i18n'

export default function ({
  addresses,
  setDeliveryAddress,
  deliveryAddress,
  setToBeModified,
  setToBeRemembered,
  toBeModified,
}) {
  const { t } = useTranslation()

  const [isModalOpen, setModalOpen] = useState(false)
  const [alreadyAskedForModification, setAlreadyAskedForModification] =
    useState(false)
  const [selectValue, setSelectValue] = useState(null)

  const handleModifyAddress = () => {
    if (
      deliveryAddress['@id'] &&
      !toBeModified &&
      !alreadyAskedForModification
    ) {
      setModalOpen(true)
    }
  }

  const onAddressSelected = value => {
    const selectedAddress = addresses.find(
      address => address.streetAddress === value,
    )

    setDeliveryAddress(selectedAddress)
  }

  /**
   * TODO :
   * Format telephone number
   */

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
              onAddressSelected(value)
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
          <Input
            onChange={e => {
              const newValue = e.target.value
              handleModifyAddress()

              setDeliveryAddress(prevState => ({
                ...prevState,
                name: newValue,
              }))
            }}
            placeholder="Nom"
            value={deliveryAddress.name}
          />
        </div>
        <div className="col-md-4">
          <Input
            onChange={e => {
              const newValue = e.target.value

              handleModifyAddress()
              setDeliveryAddress(prevState => ({
                ...prevState,
                telephone: newValue,
              }))
            }}
            placeholder="Téléphone"
            value={deliveryAddress.telephone}
          />
        </div>
        <div className="col-md-4">
          <Input
            onChange={e => {
              const newValue = e.target.value
              handleModifyAddress()
              setDeliveryAddress(prevState => ({
                ...prevState,
                contactName: newValue,
              }))
            }}
            placeholder="Contact"
            value={deliveryAddress.contactName}
          />
        </div>
        <div className="col-md-12">
          <AddressAutosuggest
            address={deliveryAddress.address}
            addresses={addresses}
            required={true}
            reportValidity={true}
            preciseOnly={true}
            onAddressSelected={(value, address) => {
              setDeliveryAddress(address)
              setSelectValue(null)
            }}
            onClear={() => {
              setDeliveryAddress(prevState => ({
                ...prevState,

                streetAddress: '',
                name: '',
                contactName: '',
                telephone: '',
              }))
            }}
          />

          <Checkbox
            onChange={e => {
              if (e.target.checked) {
                setToBeRemembered(true)
              } else {
                setToBeRemembered(false)
              }
            }}>
            Se souvenir de cette adresse
          </Checkbox>
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
          {deliveryAddress.name} - {deliveryAddress.streetAddress}
        </h4>
        <p>{t('ADDRESS_BOOK_PROP_CHANGED_DISCLAIMER')}</p>
        <div className="d-flex justify-content-center">
          <Button
            className="mr-4"
            onClick={() => {
              setToBeModified(true)
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
