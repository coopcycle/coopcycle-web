import React, { useEffect, useState, useCallback } from 'react'
import { Formik, Form, Field } from 'formik'
import AddressBookNew from '../../../../js/app/delivery/AddressBookNew'
import SwitchTimeSlotFreePicker from './SwitchTimeSlotFreePicker'
import { Input } from 'antd'
import moment from 'moment'
import DateRangePicker from '../../../../js/app/components/delivery/DateRangePicker'

function getNextRoundedTime() {
  const now = moment();
  now.add(15, 'minutes');
  const roundedMinutes = Math.ceil(now.minutes() / 5) * 5;
  if (roundedMinutes >= 60) {
    now.add(1, 'hour');
    now.minutes(roundedMinutes - 60);
  } else {
    now.minutes(roundedMinutes);
  }
  now.seconds(0);

  return now;
}

export default ({ addresses, onSubmitStatus, storeId, storeDeliveryInfos }) => {
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
  
  const [afterValue, setAfterValue] = useState(getNextRoundedTime())
  const [beforeValue, setBeforeValue] = useState(getNextRoundedTime().add(15, 'minutes'))
  const [timeSlot, setTimeSlotValue] = useState(null)
  const format = 'LL'
  
  console.log(timeSlot)
  console.log(afterValue, beforeValue)

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
  
  const areDefinedTimeSlots = useCallback(() => {
  return storeDeliveryInfos && Array.isArray(storeDeliveryInfos.timeSlots) && storeDeliveryInfos.timeSlots.length > 0;
  }, [storeDeliveryInfos])
  
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
              {areDefinedTimeSlots() ?
                <SwitchTimeSlotFreePicker
                  storeId={storeId}
                  storeDeliveryInfos={storeDeliveryInfos}
                  setTimeSlotValue={setTimeSlotValue}
                  format={format}
                  afterValue={afterValue}
                  beforeValue={beforeValue}
                  setAfterValue={setAfterValue}
                  setBeforeValue={setBeforeValue}
                /> :
                <DateRangePicker
                  format={format}
                  afterValue={afterValue}
                  beforeValue={beforeValue}
                  setAfterValue={setAfterValue}
                  setBeforeValue={setBeforeValue}
                />}

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


