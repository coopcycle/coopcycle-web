import React from 'react'
import { Field } from 'formik'
import { Select, Input } from 'antd'

export default function ({ initialValues }) {
  console.log(initialValues)

  return (
    <div>
      <h2>Informations du retrait</h2>
      <div>
        <div className="row">
          <div className="col-md-12 mb-3">
            <Field name="addresses">
              {({ field, form }) => (
                <Select
                style={{width: "100%"}}
                  {...field}
                  showSearch
                  placeholder="Rechercher une adresse enregistrée"
                  optionFilterProp="label"
                  onChange={value => form.setFieldValue(field.name, value)}
                  filterOption={(input, option) =>
                    option.label.toLowerCase().includes(input.toLowerCase())
                  }
                  options={[
                    { value: 'address_1', label: 'Adresse n°1' },
                    { value: 'address_2', label: 'Adresse n°2' },
                    { value: 'address_3', label: 'Adresse N°3' },
                  ]}
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
                />
              )}
            </Field>
          </div>
        </div>
        <div className="row">
          <div className="col-md-12">
            <Field name="comment">
              {({ field, form }) => (
                <>
                  <label htmlFor={field.name}>Commentaire</label>
                  <Input
                    {...field}
                    onChange={value => form.setFieldValue(field.name, value)}
                    placeholder="Commentaire"
                  />
                </>
              )}
            </Field>
          </div>
        </div>
      </div>
    </div>
  )
}
