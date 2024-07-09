import React, { useState } from 'react'
import _ from 'lodash'

import { Field, Formik } from 'formik'
import AddressAutosuggest from '../AddressAutosuggest'
import { useTranslation } from 'react-i18next'

export default ({initialValues, onSubmit}) => {

  const [isLoading, setLoading] = useState(false)

  const handleSubmit = async (values) => {
    setLoading(true)
    await onSubmit(values)
    setLoading(false)
  }

  const { t } = useTranslation()

  return (
    <Formik
      initialValues={initialValues}
      onSubmit={handleSubmit}
      validateOnBlur={false}
      validateOnChange={false}
    >
      {({
        values,
        errors,
        setFieldValue,
        handleSubmit,
      }) => (
        <form
          onSubmit={handleSubmit}
          autoComplete="off"
          className="p-4"
        >
          <div className="row">
            <div className={ `form-group col-md-12 ${errors.name ? 'has-error': ''}` }>
              <label className="control-label" htmlFor="name">{ t('ADMIN_WAREHOUSE_NAME_LABEL') }</label>
              <Field
                className="form-control"
                type="text"
                value={ values.name }
                name="name"
                required
                minLength="2"
              />
            </div>
          </div>
          <div className="row">
            <div className={ `form-group col-md-12 ${errors.address ? 'has-error': ''}` }>
              <label className="control-label" htmlFor="address">{ t('ADMIN_WAREHOUSE_ADDRESS_LABEL') }</label>
                <Field name="address">
                  {({ meta }) => (
                    <>
                      <AddressAutosuggest
                        autofocus={ false }
                        address={ values.address }
                        onAddressSelected={ (value, address) => {
                          const cleanAddress =
                            _.omit(address, ['isPrecise', 'latitude', 'longitude', 'addressRegion', 'geohash', 'needsGeocoding'])

                          address = {
                            ...values.address,
                            ...cleanAddress
                          }

                          setFieldValue('address', address)
                        } } />
                        {meta.touched && meta.error && <div className="error">{meta.error}</div>}
                      </>
                    )}
                </Field>
            </div>
          </div>
          <div className="row">
            <div className="input-group-btn text-center">
              <button className="btn btn-primary" type="submit" disabled={isLoading}>
                { t('SAVE_BUTTON') }
              </button>
            </div>
          </div>
        </form>
      )}
    </Formik>
  )
}