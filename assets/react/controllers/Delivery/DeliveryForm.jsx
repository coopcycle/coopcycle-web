import React, {useEffect, useState} from 'react'
import { Formik, Form } from 'formik'
import PickUp from './PickUp'
import { antdLocale } from '../../../../js/app/i18n'
import { ConfigProvider } from 'antd'
import axios from 'axios'
import AddressBookNew from '../../../../js/app/delivery/AddressBookNew'



export default function ({ isNew, storeId }) {

  const [addresses, setAddresses] = useState([])

  const [deliveryAddress, setDeliveryAddress] = useState({
    streetAddress: "",
    name: "", 
    contactName: "", 
    telephone: ""
  })

  console.log("deliveryAddress", deliveryAddress)
  
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

  const baseURL = location.protocol + '//' + location.host

  useEffect(() => {
    
    const getAddresses = async () => {
    const jwtResp = await $.getJSON(window.Routing.generate('profile_jwt'))
    const jwt = jwtResp.jwt
    const url = `${baseURL}/api/stores/${storeId}/addresses`
    const response = await axios.get(
      url,
      {
        headers: {
          Authorization: `Bearer ${jwt}`
        }
      }
    )
    const addresses = await response.data["hydra:member"]   
    setAddresses(addresses)
    }

    if (storeId) {
      getAddresses()
    }
  }, [storeId])

    

  console.log(isNew)
  console.log(storeId)
  return (
    <ConfigProvider locale={antdLocale}>
    <Formik initialValues={initialValues} onSubmit={handleSubmit}>
      {({ isSubmitting }) => (
          <Form>
            
            <AddressBookNew
              addresses={addresses}
              deliveryAddress={deliveryAddress}
              setDeliveryAddress={setDeliveryAddress}
            />
            <PickUp initialValues={initialValues} />
            
          <button type="submit" disabled={isSubmitting}>
            Soumettre
          </button>
        </Form>
      )}
      </Formik>
      </ConfigProvider>
  )
}
