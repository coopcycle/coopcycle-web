import React, { useEffect } from 'react'
import { Formik, Form } from 'formik'
// import { Select, Input } from 'antd';
import AddressBookNew from '../../../../js/app/delivery/AddressBookNew'

export default ({ addresses, deliveryAddress, setDeliveryAddress, onSubmitStatus }) => {
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
  };

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
            </Form>
          )
        }}
      </Formik>
    </div>
  )
}


