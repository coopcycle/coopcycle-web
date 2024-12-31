import React, { useCallback, useEffect, useState } from 'react'
import { Button } from 'antd'
import { Formik, Form, FieldArray } from 'formik'
import Task from './Task'
import { antdLocale } from '../../../../js/app/i18n'
import { ConfigProvider } from 'antd'
import axios from 'axios'
import moment from 'moment'
import {money} from '../../controllers/Incident/utils.js'

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
  const [calculatedPrice, setCalculatePrice] = useState(0)


  console.log("price", calculatedPrice)

    const initialValues = {
    tasks: [
      {
        type: 'PICKUP',
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
      },
      {
        type: 'DROPOFF',
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



  return (
    <ConfigProvider locale={antdLocale}>
      <Formik initialValues={initialValues} onSubmit={handleSubmit}>
        {({ values, isSubmitting, isValid }) => {
          useEffect(() => {
            const infos = {
              store: storeDeliveryInfos["@id"],   
              weight: values.tasks.find(task => task.type === "DROPOFF").weight,
              pickup: values.tasks.find(task => task.type === "PICKUP"),
              dropoff: values.tasks.find(task => task.type === "DROPOFF"),
              packages: values.tasks.find(task => task.type === "DROPOFF").packages,
              tasks: values.tasks,
            };

            const calculatePrice = async () => {
              const jwtResp = await $.getJSON(window.Routing.generate('profile_jwt'))
              const jwt = jwtResp.jwt
              const url = `${baseURL}/api/retail_prices/calculate`

              const response = await axios.post(
                url,
                infos,
                {
                  headers: {
                    Authorization: `Bearer ${jwt}`,
                    'Content-Type': 'application/ld+json',
                  },
                },
              )
              console.log(response.data)
              setCalculatePrice(response.data)

            }
            if (values.tasks.find(task => task.type === "PICKUP").address.streetAddress !== '' && values.tasks.find(task => task.type === "DROPOFF").address.streetAddress !== '') {
              calculatePrice()
            }
            
          }, [values, storeId]);

          return (
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
                        {task.type === 'DROPOFF' && index > 1 && (
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
                      <p>Multiple dropoff is not available</p>
                      <Button
                        disabled={true}
                        onClick={() => {
                          const newDropoff = {
                            type: 'DROPOFF',
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

              <div className='deliveryform__total-price'>
                <div>Total - Pricing </div>
                <div>
                  {calculatedPrice.amount 
                    ? 
                    <div>
                      <div>
                        ${money(calculatedPrice.amount)} VAT
                      </div>
                      <div>
                        ${money(calculatedPrice.amount - calculatedPrice.tax.amount)} ex. VAT
                      </div>
                    </div>
                    : 
                    <div>
                      <div>
                        ${money(0)} VAT
                      </div>
                      <div>
                        ${money(0)} ex. VAT
                      </div>
                    </div>
                  }
                </div>
              </div>

              <Button htmlType="submit" disabled={isSubmitting || !isValid}>Soumettre</Button>
            </Form>
          )
        }}
      </Formik>
    </ConfigProvider>
  )
}
