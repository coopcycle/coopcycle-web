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

export default function ({ isNew, storeId }) {

  const [addresses, setAddresses] = useState([])
  const [storeDeliveryInfos, setStoreDeliveryInfos] = useState({})
  const [calculatedPrice, setCalculatePrice] = useState(0)
  const [error, setError] = useState({ isError: false, errorMessage: ' ' })
  const [priceError, setPriceError] = useState({ isPriceError: false, priceErrorMessage: ' ' })
  
  console.log(storeDeliveryInfos)

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
        
        const taskErrors = {};

        if (!values.tasks[i].address.formatedTelephone) {
          taskErrors.address = taskErrors.address || {};
          taskErrors.address.formatedTelephone = "You must enter a telephone number.";
        } else if (!validatePhoneNumber(values.tasks[i].address.formatedTelephone)) {
          taskErrors.address = taskErrors.address || {};
          taskErrors.address.formatedTelephone = "You must enter a valid phone number.";
        }

        if (values.tasks[i] === 'DROPOFF' && storeDeliveryInfos.packagesRequired && !values.tasks[i].packages.some(item => item.quantity > 0)) {
          taskErrors.packages= "You must pick at least one package"
        }

        if (values.tasks[i] === "DROPOFF" && storeDeliveryInfos.weightRequired && !values.tasks[i].weight) {
          taskErrors.weight = "You must specify a weight"
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


  console.log(isNew)

  const handleSubmit = useCallback(
    async values => {
      const jwtResp = await $.getJSON(window.Routing.generate('profile_jwt'))
      const jwt = jwtResp.jwt
      const url = `${baseURL}/api/deliveries`

      await axios.post(
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
        .then(response => {
              console.log(values)
              console.log(response.data)
      })
        .catch(error => {
          if (error.response) {
            setError({isError: true, errorMessage: error.response.data['hydra:description']} )
            console.log("Erreur : ", error.response.data.violations[0].message)
            console.log(values)
          }
        })

    },
    [storeDeliveryInfos],
  )



  return (
    <ConfigProvider locale={antdLocale}>
      <Formik
        initialValues={initialValues}
        onSubmit={handleSubmit}
        validate={validate}
        validateOnChange={true}
        validateOnBlur={true}
      >
        {({ values, isSubmitting, isValid, handleBlur }) => {

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
                .then(response => setCalculatePrice(response.data)
                )
                .catch(error => {
                  if (error.response) {
                    setPriceError({ isPriceError: true, priceErrorMessage: error.response.data['hydra:description'] } )
                  console.log("Erreur : ", error.response.data['hydra:description'])
                }
              })

            }
            if (values.tasks.find(task => task.type === "PICKUP").address.streetAddress !== '' && values.tasks.find(task => task.type === "DROPOFF").address.streetAddress !== '') {
              calculatePrice()
            }
            
          }, [values, storeId]);

          return (
            <Form>
              {error.isError ? 
                <div className="alert alert-danger" role="alert">
                  {error.errorMessage}
                </div>
              : null}
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
                          handleBlur={handleBlur}
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
                {priceError.isPriceError ? 
                  <div className="alert alert-info" role="alert">
                    {priceError.priceErrorMessage}
                  </div>
              : null}
                
              </div>

              <Button htmlType="submit" disabled={isSubmitting || !isValid}>Soumettre</Button>
            </Form>
          )
        }}
      </Formik>
    </ConfigProvider>
  )
}
