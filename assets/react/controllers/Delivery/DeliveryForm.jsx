import React, {useEffect, useState} from 'react'
import Task from './Task'
import { antdLocale } from '../../../../js/app/i18n'
import { ConfigProvider } from 'antd'
import axios from 'axios'


const baseURL = location.protocol + '//' + location.host

export default function ({ isNew, storeId }) {
  
  const [addresses, setAddresses] = useState([])
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [storeDeliveryInfos, setStoreDeliveryInfos] = useState({})

  const handleSubmitStatus = (status) => {
    setIsSubmitting(status);
  }

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
    const fetchStoreInfos = async () => {
      const jwtResp = await $.getJSON(window.Routing.generate('profile_jwt'))
      const jwt = jwtResp.jwt

      const url = `${baseURL}/api/stores/${storeId}`

      const response = await axios.get(url, {
        headers: {
          Authorization: `Bearer ${jwt}`,
        },
      })
      setStoreDeliveryInfos(response.data)
    }
    if (storeId) {
      fetchStoreInfos()
    }
  }, [storeId])

  console.log(isNew)
  
  return (
    <ConfigProvider locale={antdLocale}>
      <Task
        addresses={addresses}
        onSubmitStatus={handleSubmitStatus}
        storeId={storeId}
        storeDeliveryInfos={storeDeliveryInfos}
      />
        <button type="submit" disabled={isSubmitting}>
          Soumettre
        </button>
      </ConfigProvider>
  )
}
