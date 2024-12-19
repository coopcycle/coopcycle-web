import React, { useEffect, useState } from 'react'
import { Formik, Form, Field } from 'formik'
import AddressBookNew from '../../../../js/app/delivery/AddressBookNew'
import SwitchTimeSlotFreePicker from './SwitchTimeSlotFreePicker'
import {Input} from 'antd'

export default ({ addresses, onSubmitStatus, storeId, storeDeliveryInfos }) => {

  const [deliveryAddress, setDeliveryAddress] = useState({
    address: {
      streetAddress: "",
      name: "", 
      contactName: "", 
      telephone: ""
      }, 
    toBeRemembered: false, 
    toBeModified: false,
    })

  // const [beforeValue, setBeforeValue] = useState(initialBeforeValue)
  // const [afterValue, setAfterValue] = useState(initialAfterValue)
  const [timeSlot, setTimeSlotValue] = useState(null)
  
  console.log("timeSlot", timeSlot)
  
  const initialValues = {
    name: '',
    contactName: '',
    streetAddress: '',
    telephone: '',
    timeSlot: '',
    commentary: '',
  }

  const handleSubmit = (values, { setSubmitting }) => {
    console.log(values)
    setSubmitting(false)
  }

  const { TextArea } = Input

  return (
    <div>
      <h2>Informations du retrait</h2>
      <Formik initialValues={initialValues} onSubmit={handleSubmit}>
        {({ isSubmitting }) => {
          useEffect(() => {
            onSubmitStatus(isSubmitting)
          }, [isSubmitting, onSubmitStatus])

          return (
            <Form>
              <AddressBookNew
                addresses={addresses}
                deliveryAddress={deliveryAddress}
                setDeliveryAddress={setDeliveryAddress}
              />
              <SwitchTimeSlotFreePicker
                storeId={storeId}
                storeDeliveryInfos={storeDeliveryInfos}
                setTimeSlotValue={setTimeSlotValue}
              />
                <label htmlFor="commentary" style={{ display: 'block', marginBottom: '0.5rem' }}>
                  Commentaire
                </label>
              <Field name="commentary">
                {({ field, form }) => (
               
                  <TextArea
                    rows={6}
                    style={{resize: "none"}}
                    autoSize={false}
                      onChange={value => form.setFieldValue(field.name, value)} />
                    
                  )}
              </Field>
            </Form>
          )
        }}
      </Formik>
    </div>
  )
}


