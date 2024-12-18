import React, {useEffect, useState} from 'react'
import PickUp from './PickUp'
import { antdLocale } from '../../../../js/app/i18n'
import { ConfigProvider } from 'antd'
import axios from 'axios'
// import AddressBookNew from '../../../../js/app/delivery/AddressBookNew'



export default function ({ isNew, storeId }) {

  const [addresses, setAddresses] = useState([])
  /**
   * deliveryAddress contains :
   * the address information as an object, 
   * if it needs to be saved (in case it's new),
   * if it needs to be modified (in case it's already saved)
   */
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

  console.log("deliveryAddress", deliveryAddress)
  

  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmitStatus = (status) => {
    setIsSubmitting(status);
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
  
  return (
    <ConfigProvider locale={antdLocale}>
      <PickUp
        addresses={addresses}
        deliveryAddress={deliveryAddress}
        setDeliveryAddress={setDeliveryAddress}
        onSubmitStatus={handleSubmitStatus}
      />
        <button type="submit" disabled={isSubmitting}>
          Soumettre
        </button>
      </ConfigProvider>
  )
}
