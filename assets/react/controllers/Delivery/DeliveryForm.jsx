import React, { useCallback, useEffect, useState } from 'react'
import { Button } from 'antd'
import { Formik, Form, FieldArray } from 'formik'
import Task from './Task'
import { antdLocale } from '../../../../js/app/i18n'
import { ConfigProvider } from 'antd'
import axios from 'axios'
import moment from 'moment'
import { money } from '../../controllers/Incident/utils.js'
import { PhoneNumberUtil } from 'google-libphonenumber'
import { getCountry } from '../../../../js/app/i18n'

const phoneUtil = PhoneNumberUtil.getInstance();

const getNextRoundedTime = () => {
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

const validatePhoneNumber = (telephone) => {
  try {
    const phoneNumber = telephone.startsWith('+')
      ? phoneUtil.parse(telephone)
      : phoneUtil.parse(telephone, getCountry());
    return phoneUtil.isValidNumber(phoneNumber);
  } catch (error) {
    return false;
  }
};



const baseURL = location.protocol + '//' + location.host

// as props we also have isNew to manage if it's a new delivery or an edit
export default function ({  storeId }) {

  const [addresses, setAddresses] = useState([])
  const [storeDeliveryInfos, setStoreDeliveryInfos] = useState({})
  const [calculatedPrice, setCalculatePrice] = useState(0)
  const [error, setError] = useState({ isError: false, errorMessage: ' ' })
  const [priceError, setPriceError] = useState({ isPriceError: false, priceErrorMessage: ' ' })
  
    const initialValues = {
    tasks: [
      {
        type: 'PICKUP',
        doneAfter: getNextRoundedTime().toISOString(),
        doneBefore: getNextRoundedTime().add(15, 'minutes').toISOString(),
        timeSlot: null,
        comments: '',
        address: {
          streetAddress: '',
          name: '',
          contactName: '',
          telephone: '',
          formattedTelephone: ''
        },
        toBeRemembered: false,
        toBeModified: false,
      },
      {
        type: 'DROPOFF',
        doneAfter: getNextRoundedTime().toISOString(),
        doneBefore: getNextRoundedTime().add(30, 'minutes').toISOString(),
        timeSlot: null,
        comments: '',
        address: {
          streetAddress: '',
          name: '',
          contactName: '',
          telephone: '',
          formattedTelephone : 's',
        },
        toBeRemembered: false,
        toBeModified: false,
        packages: [], 
        weight: 0
      },
    ],
    }
  

    const validate = (values) => {
      const errors = { tasks: [] };
      
      for (let i = 0; i < values.tasks.length; i++) {
        
        const taskErrors = {}

        let doneAfterPickup

        if (values.tasks[0].doneAfter) {
          doneAfterPickup = values.tasks[0].doneAfter
        } else if (values.tasks[0].timeSlot) {
          const after = values.tasks[0].timeSlot.slice(0, 19 )
          doneAfterPickup = after
        }

        if (!values.tasks[i].address.formatedTelephone) {
          taskErrors.address = taskErrors.address || {}; 
          taskErrors.address.formatedTelephone = "You must enter a telephone number."
        } else if (!validatePhoneNumber(values.tasks[i].address.formatedTelephone)) {
          taskErrors.address = taskErrors.address || {}; 
          taskErrors.address.formatedTelephone = "You must enter a valid phone number."
        }

        if (values.tasks[i].type === 'DROPOFF' && storeDeliveryInfos.packagesRequired && !values.tasks[i].packages.some(item => item.quantity > 0)) {
          taskErrors.packages= "You must pick at least one package"
        }

        if (values.tasks[i].type === "DROPOFF" && storeDeliveryInfos.weightRequired && !values.tasks[i].weight) {
          taskErrors.weight = "You must specify a weight"
        }

        if (values.tasks[i].type === "DROPOFF" && values.tasks[i].doneAfter) {
          const doneAfterDropoff = values.tasks[i].doneAfter
          const isWellOrdered = moment(doneAfterPickup).isBefore(doneAfterDropoff)
          if (!isWellOrdered) {
            taskErrors.doneBefore="Droppoff must be after Pickup"
          }
        }


        if (Object.keys(taskErrors).length > 0) {
          errors.tasks[i] = taskErrors
        }
      }

      return Object.keys(errors.tasks).length > 0 ? errors : {}
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

  const handleSubmit = useCallback(
    async (values) => {
      
      const jwtResp = await $.getJSON(window.Routing.generate('profile_jwt'))
      const jwt = jwtResp.jwt
      const tasksUrl = `${baseURL}/api/deliveries`
      const newAddressURL= `${baseURL}/api/me/addresses`

      await axios.post(
        tasksUrl,
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
      //   .then(response => {
          // redirect 
      // })
        .catch(error => {
          if (error.response) {
            setError({isError: true, errorMessage: error.response.data['hydra:description']} )
          }
        })
      
      for (const task of values.tasks) {
        if (task.toBeRemembered) {
          axios.post(
            newAddressURL, 
            task.address, 

            {
          headers: {
            Authorization: `Bearer ${jwt}`,
            'Content-Type': 'application/ld+json',
            },
          },
          )
            .catch(error => {
              if (error.response) {
                setError({isError: true, errorMessage:error.response.data['hydra:description']})
              }
            })
        }
      }
    },
    [storeDeliveryInfos],
  )



  return (
    <ConfigProvider locale={antdLocale}>
      <Formik
        initialValues={initialValues}
        onSubmit={handleSubmit}
        validate={validate}
        validateOnChange={false}
        validateOnBlur={false}
      >
        {({ values, isSubmitting }) => {
          
          console.log("values", values)

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

              await axios.post(
                url,
                infos,
                {
                  headers: {
                    Authorization: `Bearer ${jwt}`,
                    'Content-Type': 'application/ld+json',
                  },
                },
              )
                .then(response => {
                  setCalculatePrice(response.data)
                  setPriceError({ isPriceError: false, priceErrorMessage: ' ' })
                }
                )
                .catch(error => {
                  if (error.response) {
                    setPriceError({ isPriceError: true, priceErrorMessage: error.response.data['hydra:description'] })
                    setCalculatePrice(0)
                }
              })

            }
            if (values.tasks.find(task => task.type === "PICKUP").address.streetAddress !== '' && values.tasks.find(task => task.type === "DROPOFF").address.streetAddress !== '') {
              calculatePrice()
            }
            
          }, [values, storeDeliveryInfos]);

          return (
            <Form className='container-fluid'>
            <div className='new-delivery row'>
              
              <FieldArray name="tasks">
                {(arrayHelpers) => (
                  <div className="new-order col-sm-9"  style={{ display: 'flex', justifyContent: 'space-evenly', flexWrap: 'wrap' }}>
                    {values.tasks.map((task, index) => (
                      <div className='border p-4 mb-4' style={{maxWidth:'460px'}} key={index}>
                        <Task
                          key={index}
                          task={task}
                          index={index}
                          addresses={addresses}
                          storeId={storeId}
                          storeDeliveryInfos={storeDeliveryInfos}
                        />
                        {task.type === 'DROPOFF' && index > 1 ? (
                          <Button
                            onClick={() => arrayHelpers.remove(index)}
                            type="button"
                            className='mb-4'
                          >
                            Remove this dropoff
                          </Button>
                        ) : null}

                        {task.type === 'DROPOFF' ? 
                        <div className='mb-4' style={{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
                          <p>Multiple dropoff is not available</p>
                          <Button
                            disabled={true}
                            onClick={() => {
                              const newDropoff = {
                                type: 'DROPOFF',
                                doneAfter: getNextRoundedTime().toISOString(),
                                doneBefore: getNextRoundedTime().add(30, 'minutes').toISOString(),
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
                        </div> : null}
                      </div>
                    ))}
                    
                  </div>
                )}
              </FieldArray>
              <div className="order-informations col-sm-3">
                <div className='order-informations__total-price border-top border-bottom pt-3 pb-3 mb-4'>
                  <div className='font-weight-bold mb-2'>Total - Pricing </div>
                    <div>
                    {calculatedPrice.amount 
                      ? 
                      <div>
                        <div className='mb-1'>
                          {money(calculatedPrice.amount)} VAT
                        </div>
                        <div>
                          {money(calculatedPrice.amount - calculatedPrice.tax.amount)} ex. VAT
                        </div>
                      </div>
                      : 
                      <div>
                        <div className='mb-1'>
                          {money(0)} VAT
                        </div>
                        <div>
                          {money(0)} ex. VAT
                        </div>
                      </div>
                    }
                  </div>
                  {priceError.isPriceError ? 
                    <div className="alert alert-info mt-4" role="alert">
                      {priceError.priceErrorMessage}
                    </div>
                : null}
                
                </div>
              
                <div className='order-informations__complete-order'>
                  <Button  style={{ backgroundColor: '#F05A58', color: '#fff', height: '2.5em'}} htmlType="submit" disabled={isSubmitting}>Soumettre</Button>
                  </div>
                  
                {error.isError ? 
                  <div className="alert alert-danger mt-4" role="alert">
                    {error.errorMessage}
                  </div>
                : null}
                </div>

            </div>
            </Form>
          )
        }}
      </Formik>
    </ConfigProvider>
  )
}
