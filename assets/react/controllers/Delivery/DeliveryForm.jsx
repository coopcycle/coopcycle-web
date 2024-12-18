import React, {useEffect, useState} from 'react'
import PickUp from './PickUp'
import { antdLocale } from '../../../../js/app/i18n'
import { ConfigProvider } from 'antd'
import axios from 'axios'
// import AddressBookNew from '../../../../js/app/delivery/AddressBookNew'



export default function ({ isNew, storeId }) {

  const [addresses, setAddresses] = useState([])
  const [timeSlotsLabel, setTimeSlotsLabel] = useState([])
  const [timeSlotsOptions, setTimeSlotsOptions] = useState({})
  const [defaultTimeSlotName, setDefaultTimeSlotName] = useState("")
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

  useEffect(() => {
    const getTimeSlotsOptions = async () => {
     const jwtResp = await $.getJSON(window.Routing.generate('profile_jwt'))
    const jwt = jwtResp.jwt
      const url = `${baseURL}/api/stores/${storeId}/time_slots`
      
      const response = await axios.get(
      url,
      {
        headers: {
          Authorization: `Bearer ${jwt}`
        }
      }
      )
      const timeSlotsLabel = await response.data["hydra:member"]   
      setTimeSlotsLabel(timeSlotsLabel)
    }
     if (storeId) {
        getTimeSlotsOptions()
      }
  }, [storeId])
  
  useEffect(() => {
    // on vient récupérer 
    const createTimeSlotsObjects = async () => {
      const jwtResp = await $.getJSON(window.Routing.generate('profile_jwt'))
      const jwt = jwtResp.jwt
      const timeSlotChoices = {}
      for (const label of timeSlotsLabel) {
        const name = label.name
        const id = label["@id"]
        const url = `${baseURL}${id}/choices`
        const response = await axios.get(
          url, 
        {
        headers: {
          Authorization: `Bearer ${jwt}`
        }
        }
        )
        const choices = response.data.choices

        const values = choices.map(choice => (choice.value))
        timeSlotChoices[name]= values
      }
      setTimeSlotsOptions(timeSlotChoices)
    }
    
    createTimeSlotsObjects()
    
  }, [timeSlotsLabel])

  useEffect(() => {
    const generateDefaultTimeSlotOptions = async () => {
      const jwtResp = await $.getJSON(window.Routing.generate('profile_jwt'))
      const jwt = jwtResp.jwt

      const url = `${baseURL}/api/stores/${storeId}`

      const storeInfos = await axios.get(
        url, 
        {
        headers: {
          Authorization: `Bearer ${jwt}`
        }
        }
      )
      const defaultTimeSlot = storeInfos.data.timeSlot
      const getDefaultTimeSlotName = () => {
        const timeslot = timeSlotsLabel.find(label => label["@id"] = defaultTimeSlot)
        
        return timeslot.name
      }

      const defaultTimeSlotName = getDefaultTimeSlotName()
      setDefaultTimeSlotName(defaultTimeSlotName)    
    }
    generateDefaultTimeSlotOptions()
}, [timeSlotsOptions])

  console.log(isNew)
  
  return (
    <ConfigProvider locale={antdLocale}>
      <PickUp
        addresses={addresses}
        deliveryAddress={deliveryAddress}
        setDeliveryAddress={setDeliveryAddress}
        onSubmitStatus={handleSubmitStatus}
        timeSlotsOptions={timeSlotsOptions}
        defaultTimeSlotName={defaultTimeSlotName}
      />
        <button type="submit" disabled={isSubmitting}>
          Soumettre
        </button>
      </ConfigProvider>
  )
}
