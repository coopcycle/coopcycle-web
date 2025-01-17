import React, { useCallback, useEffect, useRef, useState } from 'react'
import { Button } from 'antd'
import { Formik, Form, FieldArray } from 'formik'
import Task from '../../../../js/app/components/delivery-form/Task.js'
import { antdLocale } from '../../../../js/app/i18n'
import { ConfigProvider } from 'antd'
import moment from 'moment'
import { money } from '../../controllers/Incident/utils.js'
import Map from '../../../../js/app/components/delivery-form/Map.js'
import Spinner from '../../../../js/app/components/core/Spinner.js'
import _ from 'lodash'


import { PhoneNumberUtil } from 'google-libphonenumber'
import { getCountry } from '../../../../js/app/i18n'
import { useTranslation } from 'react-i18next'
import { parsePhoneNumberFromString } from 'libphonenumber-js'


import "./DeliveryForm.scss"

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

function getFormattedValue(value) {
  if (typeof value === 'string') {
    const phoneNumber = parsePhoneNumberFromString(
      value,
      (getCountry() || 'fr').toUpperCase(),
    )
    return phoneNumber ? phoneNumber.formatNational() : value
  }
  return value
}

const dropoffSchema = {
  type: 'DROPOFF',
  after: getNextRoundedTime().toISOString(),
  before: getNextRoundedTime().add(60, 'minutes').toISOString(),
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
  after: getNextRoundedTime().toISOString(),
  before: getNextRoundedTime().add(60, 'minutes').toISOString(),
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

export default function ({ storeId, deliveryId }) {
  
  const httpClient = new window._auth.httpClient()

  const [addresses, setAddresses] = useState([])
  const [storeDeliveryInfos, setStoreDeliveryInfos] = useState({})
  const [calculatedPrice, setCalculatePrice] = useState(0)
  const [error, setError] = useState({ isError: false, errorMessage: ' ' })
  const [priceError, setPriceError] = useState({ isPriceError: false, priceErrorMessage: ' ' })
  const [storePackages, setStorePackages] = useState(null)

  const [initialValues, setInitialValues] = useState({
    tasks: [
      pickupSchema,
      dropoffSchema,
    ]
  })
  const [isLoading, setIsLoading] = useState(Boolean(deliveryId))

  const isAdmin = true

  const { t } = useTranslation()
  
  const validate = (values) => {
    const errors = { tasks: [] };
    
    for (let i = 0; i < values.tasks.length; i++) {
      
      const taskErrors = {}

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

  // Could not figure out why, but sometimes Formik "re-renders" even if the values are the same.
  // so i store a ref to previous values to avoid re-calculating the price.
  const previousValues = useRef(initialValues);

  useEffect(() => {
    const deliveryURL = `${baseURL}/api/deliveries/${deliveryId}`
    const addressesURL = `${baseURL}/api/stores/${storeId}/addresses`
    const storeURL = `${baseURL}/api/stores/${storeId}`
    const packagesURL = `${baseURL}/api/stores/${storeId}/packages`

    if (deliveryId) {
        Promise.all([
        httpClient.get(deliveryURL),
        httpClient.get(addressesURL),
        httpClient.get(storeURL),
        httpClient.get(packagesURL)
        ]).then(values => {
          const [delivery, addresses, storeInfos, packages] = values

          const storePackages = packages.response['hydra:member']

          if (storePackages.length > 0) {
            setStorePackages(storePackages)
          }

          //we delete duplication of data as we only modify tasks to avoid potential conflicts/confusions
          delete delivery.response.dropoff
          delete delivery.response.pickup

          delivery.response.tasks.forEach(task => {
            const formattedTelephone = getFormattedValue(task.address.telephone)
            task.address.formattedTelephone = formattedTelephone
          })

          previousValues.current = delivery.response

          setInitialValues(delivery.response)
          setAddresses(addresses.response['hydra:member'])
          setStoreDeliveryInfos(storeInfos.response)
          setIsLoading(false)
      })
    } else {
        Promise.all([
        httpClient.get(addressesURL),
        httpClient.get(storeURL),
        httpClient.get(packagesURL)
        ]).then(values => {
          const [addresses, storeInfos, packages] = values
          
          const storePackages = packages.response['hydra:member']
          if (storePackages.length > 0) {
            setStorePackages(storePackages)
          }

          setAddresses(addresses.response['hydra:member'])
          setStoreDeliveryInfos(storeInfos.response)
          setIsLoading(false)
      })
    }
  }, [deliveryId, storeId])


  const handleSubmit = useCallback(async (values) => {
    const saveAddressUrl = `${baseURL}/api/stores/${storeId}/addresses`
    
    const getUrl = (deliveryId) => {
      if (deliveryId) {
        const editDeliveryURL = `${baseURL}/api/deliveries/${deliveryId}`
        return editDeliveryURL
      } else {
        const createDeliveryUrl = `${baseURL}/api/deliveries`
        return createDeliveryUrl
      }
    }

    const createOrEditADelivery = async (deliveryId) => {
      const url = getUrl(deliveryId);
      const method = deliveryId ? 'put' : 'post';
      
      return await httpClient[method](url, {
        store: storeDeliveryInfos['@id'],
        tasks: values.tasks
      });
    }

    const {response, error} = await createOrEditADelivery(deliveryId)

    if (error) {
      setError({ isError: true, errorMessage: error.response.data['hydra:description'] })
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

      // TODO : when we are not on the beta URL/page anymore for this form, redirect to document.refferer
      window.location = "/admin/deliveries";
    }
  }, [storeDeliveryInfos])


  const getPrice = (values) => {

    if (_.isEqual(previousValues.current, values)) {
      return
    }

    previousValues.current = values

    const tasksCopy = structuredClone(values.tasks)
    const tasksWithoutId = tasksCopy.map(task => {
          if (task["@id"]) {
            delete task["@id"]
          }
          return task
        })
      
      let packages = []

      for (const task of values.tasks) {
        if (task.packages && task.type ==="DROPOFF") {
          packages.push(...task.packages)
        }
      }
      
      const mergedPackages = _(packages)
        .groupBy('type') 
        .map((items, type) => ({
          type, 
          quantity: _.sumBy(items, 'quantity'), 
        }))
        .value()

      let totalWeight = 0

      for (const task of values.tasks) {
        if (task.weight && task.type ==="DROPOFF") {
          totalWeight+= task.weight 
        }
      }
      
      const infos = {
        store: storeDeliveryInfos["@id"],
        weight: totalWeight,
        packages: mergedPackages,
        tasks: tasksWithoutId,
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
      if (values.tasks.every(task => task.address.streetAddress)) {
        calculatePrice()
      }

  }

  return (
    isLoading ? 
      <div className="delivery-spinner">
        <Spinner />
      </div>
      :
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
                getPrice(values)
            }, [values]);

            return (
              <Form >
                <div className='delivery-form' >

                  <FieldArray name="tasks">
                    {(arrayHelpers) => (
                      <div className="new-order">
                       
                        <div className="new-order__pickups">
                          {values.tasks
                            .filter((task) => task.type === 'PICKUP')
                            .map((task) => {
                              const originalIndex = values.tasks.findIndex(t => t === task);
                              return (
                                <div className='new-order__pickups__item' key={originalIndex}>
                                  <Task
                                    deliveryId={deliveryId}
                                    key={originalIndex}
                                    task={task}
                                    index={originalIndex}
                                    addresses={addresses}
                                    storeId={storeId}
                                    storeDeliveryInfos={storeDeliveryInfos}
                                    packages={storePackages}
                                    isAdmin={isAdmin}
                                  />
                                </div>
                              );
                            })}
                        </div>

                        
                        <div className="new-order__dropoffs" style={{ display: 'flex', flexDirection: 'column' }}>
                          {values.tasks
                            .filter((task) => task.type === 'DROPOFF')
                            .map((task) => {
                              const originalIndex = values.tasks.findIndex(t => t === task);
                              return (
                                <div className='new-order__dropoffs__item' key={originalIndex}>
                                  <Task
                                    deliveryId={deliveryId}
                                    index={originalIndex}
                                    addresses={addresses}
                                    storeId={storeId}
                                    storeDeliveryInfos={storeDeliveryInfos}
                                    onAdd={arrayHelpers.push}
                                    dropoffSchema={dropoffSchema}
                                    onRemove={arrayHelpers.remove}
                                    showRemoveButton={originalIndex > 1}
                                    showAddButton={originalIndex === values.tasks.length - 1}
                                    packages={storePackages}
                                    isAdmin= {isAdmin}
                                  />
                                </div>
                              );
                            })}
                        </div>
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
                        type="primary"
                        style={{ height: '2.5em' }}
                        htmlType="submit" disabled={isSubmitting || deliveryId && isAdmin === false}>
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
