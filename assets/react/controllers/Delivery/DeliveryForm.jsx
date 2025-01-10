import React, { useCallback, useEffect, useState } from 'react'
import { Button } from 'antd'
import { Formik, Form, FieldArray } from 'formik'
import Task from '../../../../js/app/components/delivery-form/Task.js'
import { antdLocale } from '../../../../js/app/i18n'
import { ConfigProvider } from 'antd'
import moment from 'moment'
import { money } from '../../controllers/Incident/utils.js'
import Map from '../../../../js/app/components/delivery-form/Map.js'


import { PhoneNumberUtil } from 'google-libphonenumber'
import { getCountry } from '../../../../js/app/i18n' 
import { useTranslation } from 'react-i18next'

import "./DeliveryForm.scss"

const httpClient = new window._auth.httpClient()


/** used in case of phone validation */
const phoneUtil = PhoneNumberUtil.getInstance();

const getNextRoundedTime = () => {
  const now = moment()
  now.add(60, 'minutes')
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

/** Te be revamp for store as telephone is needed */
const validatePhoneNumber = (telephone) => {
  if (telephone) {
    try {
      const phoneNumber = telephone.startsWith('+')
        ? phoneUtil.parse(telephone)
        : phoneUtil.parse(telephone, getCountry())
      return phoneUtil.isValidNumber(phoneNumber)
    } catch (error) {
      return false
    }
  } else {
    return true
  }
};

const dropoffSchema = {
  type: 'DROPOFF',
  doneAfter: getNextRoundedTime().toISOString(),
  doneBefore: getNextRoundedTime().add(60, 'minutes').toISOString(),
  timeSlot: null,
  comments: '',
  address: {
    streetAddress: '',
    name: '',
    contactName: '',
    telephone: '',
    },
  updateInStoreAddresses: false,
  packages: [],
  weight: 0
  };

const pickupSchema = {
  type: 'PICKUP',
    doneAfter: getNextRoundedTime().toISOString(),
    doneBefore: getNextRoundedTime().add(60, 'minutes').toISOString(),
    timeSlot: null,
    comments: '',
    address: {
      streetAddress: '',
      name: '',
      contactName: '',
      telephone: null,
      formattedTelephone: null
    },
    saveInStoreAddresses: false,
    updateInStoreAddresses: false,
}


const baseURL = location.protocol + '//' + location.host

// as props we also have isNew to manage if it's a new delivery or an edit
export default function ({  storeId }) {

  const [addresses, setAddresses] = useState([])
  const [storeDeliveryInfos, setStoreDeliveryInfos] = useState({})
  const [calculatedPrice, setCalculatePrice] = useState(0)
  const [error, setError] = useState({ isError: false, errorMessage: ' ' })
  const [priceError, setPriceError] = useState({ isPriceError: false, priceErrorMessage: ' ' })
  

  
    const { t } = useTranslation()

  const initialValues = {
    tasks: [
      pickupSchema,
      dropoffSchema,
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

         if (values.tasks[i].type === "DROPOFF" && values.tasks[i].doneAfter) {
          const doneAfterDropoff = values.tasks[i].doneAfter
          const isWellOrdered = moment(doneAfterPickup).isBefore(doneAfterDropoff)
          if (!isWellOrdered) {
            taskErrors.doneBefore=t("DELIVERY_FORM_ERROR_HOUR")
          }
        }

        /** As the new form is for now only use by admin, they're authorized to create without phone. To be add for store */

        // if (!values.tasks[i].address.formattedTelephone) {
        //   taskErrors.address = taskErrors.address || {}; 
        //   taskErrors.address.formattedTelephone = t("DELIVERY_FORM_ERROR_TELEPHONE")
        // } 
        
        if (!validatePhoneNumber(values.tasks[i].address.formattedTelephone)) {
          taskErrors.address = taskErrors.address || {}; 
          taskErrors.address.formattedTelephone = t("ADMIN_DASHBOARD_TASK_FORM_TELEPHONE_ERROR")
        }

        if (values.tasks[i].type === 'DROPOFF' && storeDeliveryInfos.packagesRequired && !values.tasks[i].packages.some(item => item.quantity > 0)) {
          taskErrors.packages= t("DELIVERY_FORM_ERROR_PACKAGES")
        }

        if (values.tasks[i].type === "DROPOFF" && storeDeliveryInfos.weightRequired && !values.tasks[i].weight) {
          taskErrors.weight = t("DELIVERY_FORM_ERROR_WEIGHT")
        }

        if (Object.keys(taskErrors).length > 0) {
          errors.tasks[i] = taskErrors
        }
      }

      return Object.keys(errors.tasks).length > 0 ? errors : {}
  }

  useEffect(() => {
    const getAddresses = async () => {

      const url = `${baseURL}/api/stores/${storeId}/addresses`
      const {response} = await httpClient.get(url)

      if (response) {
        const addresses = response['hydra:member']
        setAddresses(addresses)
      }
    }

    if (storeId) {
      getAddresses()
    }
  }, [storeId])


  useEffect(() => {
    const fetchStoreInfos = async () => {
      const url = `${baseURL}/api/stores/${storeId}`

      const { response } = await httpClient.get(url)
      
      if (response) {
        setStoreDeliveryInfos(response)
      }
    }
    fetchStoreInfos()
  }, [storeId])

  const handleSubmit = useCallback(async (values) => {
    const createDeliveryUrl = `${baseURL}/api/deliveries`
    const saveAddressUrl = `${baseURL}/api/stores/${storeId}/addresses`
    
    const {response,error} = await httpClient.post(createDeliveryUrl, {store: storeDeliveryInfos['@id'],
          tasks: values.tasks}
    )
    if (error) {
      setError({isError: true, errorMessage:error.response.data['hydra:description']})
      return
    }

    if (response) {
      for (const task of values.tasks) {
        if (task.saveInStoreAddresses) {
          await httpClient.post(saveAddressUrl, task.address)
          if (error) {
            setError({ isError: true, errorMessage: error.response.data['hydra:description'] })
            return
          }
        }
        if (task.updateInStoreAddresses) {
          await httpClient.patch(`${baseURL}${task.address['@id']}`, task.address)
          if (error) {
            setError({ isError: true, errorMessage: error.response.data['hydra:description'] })
            return
          }
        }
      }
      window.history.go(-2);
    }
  }, [storeDeliveryInfos])

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

            console.log(values)
          
          useEffect(() => {

            const infos = {
              store: storeDeliveryInfos["@id"],   
              weight: values.tasks.find(task => task.type === "DROPOFF").weight,
              packages: values.tasks.find(task => task.type === "DROPOFF").packages,
              tasks: values.tasks,
            };

            const calculatePrice = async () => {
              const url = `${baseURL}/api/retail_prices/calculate`

              const { response, error } = await httpClient.post(url, infos)
              
              if (error) {
                setPriceError({ isPriceError: true, priceErrorMessage: error.response.data['hydra:description'] })
                setCalculatePrice(0)
              }

              if (response) {
                setCalculatePrice(response)
                setPriceError({ isPriceError: false, priceErrorMessage: ' ' })
              }

            }
            if (values.tasks.find(task => task.type === "PICKUP").address.streetAddress !== '' && values.tasks.find(task => task.type === "DROPOFF").address.streetAddress !== '') {
              calculatePrice()
            }
            
          }, [values, storeDeliveryInfos]);

          return (
            <Form >
            <div className='delivery-form' >
              
              <FieldArray name="tasks">
                {(arrayHelpers) => (
                  <div className="new-order" >
                    {values.tasks.map((task, index) => (
                      <div className='new-order__item border p-4 mb-4' key={index}>
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
                            {t("DELIVERY_FORM_REMOVE_DROPOFF")}
                          </Button>
                        ) : null}

                        {task.type === 'DROPOFF' ? 
                        <div className='mb-4' style={{display: 'flex', justifyContent: 'space-between', alignItems: 'center'}}>
                          <p>{t("DELIVERY_FORM_MULTIDROPOFF")}</p>
                          <Button
                            disabled={false}
                            onClick={() => {
                              arrayHelpers.push(dropoffSchema);
                            }}
                          >
                            {t("DELIVERY_FORM_ADD_DROPOFF")}
                          </Button>
                        </div> : null}
                      </div>
                    ))}
                    
                  </div>
                )}
              </FieldArray>
    
              <div className="order-informations"> 
                <div className="order-informations__map">
                    <Map
                      storeDeliveryInfos={storeDeliveryInfos}
                      tasks={values.tasks}
                    />
                </div>  
                <div className='order-informations__total-price border-top border-bottom pt-3 pb-3 mb-4'>
                  <div className='font-weight-bold mb-2'>{t("DELIVERY_FORM_TOTAL_PRICE")} </div>
                    <div>
                    {calculatedPrice.amount 
                      ? 
                      <div>
                        <div className='mb-1'>
                          {money(calculatedPrice.amount)} {t("DELIVERY_FORM_TOTAL_VAT")}
                        </div>
                        <div>
                          {money(calculatedPrice.amount - calculatedPrice.tax.amount)} {t("DELIVERY_FORM_TOTAL_EX_VAT")}
                        </div>
                      </div>
                      : 
                      <div>
                        <div className='mb-1'>
                          {money(0)} {t("DELIVERY_FORM_TOTAL_VAT")}
                        </div>
                        <div>
                          {money(0)} {t("DELIVERY_FORM_TOTAL_EX_VAT")}
                        </div>
                      </div>
                    }
                  </div>
                  {priceError.isPriceError ? 
                    <div className="alert alert-danger mt-4" role="alert">
                      {priceError.priceErrorMessage}
                    </div>
                : null}
                
                </div>
                  
              
                <div className='order-informations__complete-order'>
                    <Button
                      style={{ backgroundColor: '#F05A58', color: '#fff', height: '2.5em' }}
                      htmlType="submit" disabled={isSubmitting}>
                      {t("DELIVERY_FORM_SUBMIT")}
                    </Button>
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
