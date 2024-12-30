import React, { useCallback, useEffect, useState } from 'react'
import { Button } from 'antd'
import { Formik, Form, FieldArray } from 'formik'
import Task from './Task'
import { antdLocale } from '../../../../js/app/i18n'
import { ConfigProvider } from 'antd'
import axios from 'axios'
import moment from 'moment'

function getNextRoundedTime() {
  const now = moment()
  now.add(15, 'minutes')
  const roundedMinutes = Math.ceil(now.minutes() / 5) * 5
  if (roundedMinutes >= 60) {
    now.add(1, 'hour')
    now.minutes(roundedMinutes - 60)
  } else {
    now.minutes(roundedMinutes)
  }
  now.seconds(0)

  return now
}

const baseURL = location.protocol + '//' + location.host

export default function ({ isNew, storeId }) {
  /**TODO :
   * Format phone number
   */

  const [addresses, setAddresses] = useState([])
  const [storeDeliveryInfos, setStoreDeliveryInfos] = useState({})

  useEffect(() => {
    const getAddresses = async () => {
      const jwtResp = await $.getJSON(window.Routing.generate('profile_jwt'))
      const jwt = jwtResp.jwt
      const url = `${baseURL}/api/stores/${storeId}/addresses`
      const response = await axios.get(url, {
        headers: {
          Authorization: `Bearer ${jwt}`,
        },
      })
      const addresses = await response.data['hydra:member']
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

  // réécrire avec values
  const handleSubmit = useCallback(
    async values => {
      const jwtResp = await $.getJSON(window.Routing.generate('profile_jwt'))
      const jwt = jwtResp.jwt
      const url = `${baseURL}/api/deliveries`

      const response = await axios.post(
        url,
        {
          store: storeDeliveryInfos['@id'],
          tasks: values.tasks,
        },
        {
          headers: {
            Authorization: `Bearer ${jwt}`,
            'Content-Type': 'application/ld+json',
          },
        },
      )

      console.log(values)
      console.log(response.data)

    },
    [storeDeliveryInfos],
  )

  const initialValues = {
    tasks: [
      {
        type: 'pickup',
        afterValue: getNextRoundedTime().toISOString(),
        beforeValue: getNextRoundedTime().add(15, 'minutes').toISOString(),
        timeSlot: null,
        comments: '',
        address: {
          streetAddress: '',
          name: '',
          contactName: '',
          telephone: '',
        },
        toBeRemembered: false,
        toBeModified: false,
        packages: [], 
        weight: 0
      },
      {
        type: 'dropoff',
        afterValue: getNextRoundedTime().toISOString(),
        beforeValue: getNextRoundedTime().add(30, 'minutes').toISOString(),
        timeSlot: null,
        comments: '',
        address: {
          streetAddress: '',
          name: '',
          contactName: '',
          telephone: '',
        },
        toBeRemembered: false,
        toBeModified: false,
        packages: [], 
        weight: 0
      },
    ],
  }

  return (
    <ConfigProvider locale={antdLocale}>
      <Formik initialValues={initialValues} onSubmit={handleSubmit}>
        {({ values }) => (
          <Form>
            <FieldArray name="tasks">
              {(arrayHelpers) => (
                <>
                        {values.tasks.map((task, index) => (
                    <div key={index}>
                    <Task
                      key={index}
                      task={task}
                      index={index}
                      addresses={addresses}
                      storeId={storeId}
                      storeDeliveryInfos={storeDeliveryInfos}
                      
                            />
                            {task.type === 'dropoff' && index > 1 && (
                        <Button
                          onClick={() => arrayHelpers.remove(index)}
                          type="button"
                        >
                          Remove this dropoff
                        </Button>
                      )}
                    </div>
                  ))}
                  <div>
                    <p>Multiple dropoff is available</p>
                    <Button
                      onClick={() => {
                        const newDropoff = {
                          type: 'dropoff',
                          afterValue: getNextRoundedTime().toISOString(),
                          beforeValue: getNextRoundedTime().add(30, 'minutes').toISOString(),
                          timeSlot: null,
                          comments: '',
                          address: {
                            streetAddress: '',
                            name: '',
                            contactName: '',
                            telephone: '',
                          },
                          toBeRemembered: false,
                          toBeModified: false,
                          packages: [],
                          weight: 0
                        };
                        arrayHelpers.push(newDropoff);
                      }}
                    >
                      Add a new dropoff
                    </Button>
                  </div>
                </>
              )}
            </FieldArray>
            <button type="submit">Soumettre</button>
          </Form>
        )}
      </Formik>
    </ConfigProvider>
  )
}
