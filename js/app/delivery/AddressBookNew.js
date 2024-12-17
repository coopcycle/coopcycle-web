import './AddressBook.scss'
import React from 'react'
import { Field } from 'formik'
import { Input, Select } from 'antd'

export default function ({ addresses, setDeliveryAddress, deliveryAddress }) {
  const onAddressSelected = value => {
    const selectedAddress = addresses.find(
      address => address.streetAddress === value,
    )

    setDeliveryAddress({
      streetAddress: selectedAddress.streetAddress,
      name: selectedAddress.name,
      contactName: selectedAddress.contactName,
      telephone: selectedAddress.telephone,
    })
  }

  /**
   * TODO :
   * Format telephone number
   * Add AddressAutoSuggest
   */

  return (
    <>
      <div className="row">
        <div className="col-md-12 mb-3">
          <Field name="addresses">
            {({ field, form }) => (
              <Select
                style={{ width: '100%' }}
                {...field}
                showSearch
                placeholder="Rechercher une adresse enregistrée"
                optionFilterProp="label"
                onChange={value => {
                  form.setFieldValue(field.name, value)
                  onAddressSelected(value)
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
            )}
          </Field>
        </div>
      </div>
      <div className="row mb-3">
        <div className="col-md-4">
          <Field name="name">
            {({ field, form }) => (
              <Input
                {...field}
                onChange={value => form.setFieldValue(field.name, value)}
                placeholder="Nom"
                value={deliveryAddress.name}
              />
            )}
          </Field>
        </div>
        <div className="col-md-4">
          <Field name="telephone">
            {({ field, form }) => (
              <Input
                {...field}
                onChange={value => form.setFieldValue(field.name, value)}
                placeholder="Téléphone"
                value={deliveryAddress.telephone}
              />
            )}
          </Field>
        </div>
        <div className="col-md-4">
          <Field name="contactName">
            {({ field, form }) => (
              <Input
                {...field}
                onChange={value => form.setFieldValue(field.name, value)}
                placeholder="Contact"
                value={deliveryAddress.contactName}
              />
            )}
          </Field>
        </div>
        <div className="col-md-12">
          <Field name="streetAddress">
            {/* il faut que ça soit remplacé par AddresseAutoSuggest */}
            {({ field, form }) => (
              <Input
                {...field}
                onChange={value => form.setFieldValue(field.name, value)}
                placeholder="Adresse"
              />
            )}
          </Field>
        </div>
      </div>
    </>
  )
}
