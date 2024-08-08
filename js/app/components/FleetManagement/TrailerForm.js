import React, { useState } from 'react'
import CompactPicker from 'react-color/lib/Compact'

import { Field, Formik } from 'formik'
import Select from 'react-select'
import { useTranslation } from 'react-i18next'

export default ({initialValues, onSubmit, vehicles, closeModal}) => {

  const { t } = useTranslation()

  const [isLoading, setLoading] = useState(false)

  const handleSubmit = async (values) => {
    setLoading(true)
    await onSubmit({
      ...values,
      maxWeight: values.maxWeight * 1000,  // convert to gramms
      isElectric: values.isElectric || false // force-define this value as the checkox component seems not controlled in Formik
    })
    setLoading(false)
    closeModal()
  }

  initialValues = {
    ...initialValues,
    maxWeight: initialValues.maxWeight ? initialValues.maxWeight / 1000 : null,
  }

  const validate = (values) => {
    // we use mostly buil-int HTML validation
    const errors = {}

    if(!values.color || (values.isElectric && !values.electricRange)) {
      errors.electricRange = t('FORM_REQUIRED')
    }
  }

  return (
    <div className="row">
      <div className="col-md-8 col-md-offset-2">
        <div className="modal-header">
          <h4 className="modal-title">
          { initialValues['@id'] ? t('ADMIN_VEHICLE_UPDATE_TRAILER_FORM_TITLE') : t('ADMIN_VEHICLE_TRAILER_FORM_TITLE') }
            <a className="pull-right text-reset" onClick={ () => closeModal() }><i className="fa fa-close"></i></a>
          </h4>
        </div>
        <Formik
          initialValues={initialValues}
          validate={validate}
          onSubmit={handleSubmit}
          validateOnBlur={false}
          validateOnChange={false}
        >
          {({
            values,
            errors,
            touched,
            setFieldValue,
            handleSubmit,
          }) => (
            <form
              onSubmit={handleSubmit}
              autoComplete="off"
              className="p-4"
            >
              <div className={ `form-group${errors.name ? 'has-error': ''}` }>
                <div className="row">
                  <div className="col-md-12">
                  <label className="control-label" htmlFor="name">{ t('ADMIN_VEHICLE_NAME_LABEL') }</label>
                    <Field
                      className="form-control"
                      type="text"
                      value={ values.name }
                      name="name"
                      minLength="2"
                      required
                    />
                    { errors.name && touched.name && (
                      <div className="has-error px-4">
                        <small className="help-block">{ errors.name }</small>
                      </div>
                    )}
                  </div>
                </div>
              </div>
              <div className={ `form-group ${errors.color ? 'has-error': ''}` }>
                <label className="control-label" htmlFor="maxWeight">{ t('ADMIN_VEHICLE_COLOR_LABEL') }</label>
                  <div className="row">
                    <div className="col-md-12">
                      <Field
                        name="color"
                        minLength="7"
                        maxlength="7"
                        pattern="#[\d\w]{6}"
                        required
                      >
                        {() => (
                          <CompactPicker
                            color={ values.color }
                            onChangeComplete={ color => {
                              setFieldValue('color', color.hex)
                            }} />
                        )}
                      </Field>
                      { errors.color && touched.color && (
                        <div className="has-error px-4">
                          <small className="help-block">{ errors.color }</small>
                        </div>
                      )}
                    </div>
                  </div>
                </div>
                <div className="row form-group">
                  <div className="col-md-4">
                    <div className={ `${errors.maxWeight ? 'has-error': ''}` }>
                      <label className="control-label" htmlFor="maxWeight">{ t('ADMIN_VEHICLE_MAX_WEIGHT_LABEL') }</label>
                      <Field
                        className="form-control"
                        type="number"
                        value={ values.maxWeight }
                        name="maxWeight"
                        required
                      />
                      { errors.maxWeight && touched.maxWeight && (
                        <div className="has-error px-4">
                          <small className="help-block">{ errors.maxWeight }</small>
                        </div>
                      )}
                    </div>
                  </div>
                  <div className="col-md-offset-4 col-md-4">
                    <div className={ `${errors.maxVolumeUnits ? 'has-error': ''}` }>
                      <label className="control-label" htmlFor="maxVolumeUnits">{ t('ADMIN_VEHICLE_MAX_VOLUME_UNITS_LABEL') }</label>
                      <Field
                        className="form-control"
                        type="number"
                        value={ values.maxVolumeUnits }
                        name="maxVolumeUnits"
                        required
                      />
                      { errors.maxVolumeUnits && touched.maxVolumeUnits && (
                        <div className="has-error px-4">
                          <small className="help-block">{ errors.maxVolumeUnits }</small>
                        </div>
                      )}
                    </div>
                  </div>
              </div>
              <div className="row form-group">
                <div className="col-md-2">
                  <div className={ `${errors.isElectric ? 'has-error': ''}` }>
                    <label className="control-label pr-2" htmlFor="isElectric">{ t('ADMIN_VEHICLE_IS_ELECTRIC_LABEL') }</label>
                    <Field
                      type="checkbox"
                      name="isElectric"
                    />
                    { errors.isElectric && touched.isElectric && (
                      <div className="has-error px-4">
                        <small className="help-block">{ errors.isElectric }</small>
                      </div>
                    )}
                  </div>
                </div>
                <div className="col-md-4 col-md-offset-6">
                  { values.isElectric ?
                    <div className={ `${errors.electricRange ? 'has-error': ''}` }>
                      <label className="control-label" htmlFor="electricRange">{ t('ADMIN_VEHICLE_ELECTRIC_RANGE_LABEL') }</label>
                      <Field
                        className="form-control"
                        type="number"
                        value={ values.electricRange }
                        name="electricRange"
                      />
                      { errors.electricRange && touched.electricRange && (
                        <div className="has-error px-4">
                          <small className="help-block">{ errors.electricRange }</small>
                        </div>
                      )}
                    </div>
                    : null
                  }
                </div>
              </div>
              <div className="row form-group">
                <div className="col-md-12">
                  <div className={ `${errors.compatibleVehicles ? 'has-error': ''}` }>
                    <label className="control-label" htmlFor="compatibleVehicles">{ t('ADMIN_VEHICLE_COMPATIBLE_VEHICLES_LABEL') }</label>
                    <Field
                      className="form-control"
                      name="compatibleVehicles"
                    >
                    { () =>
                      <Select
                        isMulti={true}
                        // https://github.com/coopcycle/coopcycle-web/issues/774
                        // https://github.com/JedWatson/react-select/issues/3030
                        menuPortalTarget={document.body}
                        defaultValue={
                          initialValues.compatibleVehicles.map(vehicleId => {
                            const vehicle = vehicles.find(v => v['@id'] === vehicleId)
                            return {value: vehicle['@id'], label: vehicle.name}
                        })}
                        options={vehicles.map(vehicle => {return {value: vehicle['@id'], label: vehicle.name}})}
                        onChange={(selected) => { setFieldValue('compatibleVehicles', selected.map(opt => opt.value)) }}
                        placeholder={ t('ADMIN_VEHICLE_COMPATIBLE_VEHICLES_LABEL') }
                      />
                    }
                    </Field>
                    { errors.compatibleVehicles && touched.compatibleVehicles && (
                      <div className="has-error px-4">
                        <small className="help-block">{ errors.compatibleVehicles }</small>
                      </div>
                    )}
                  </div>
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
      </div>
    </div>
  )
}