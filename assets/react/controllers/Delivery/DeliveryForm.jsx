import React, { useCallback, useEffect, useState } from 'react'
import { Button } from 'antd'
import { Formik, Form, FieldArray } from 'formik'
import Task from '../../../../js/app/components/delivery-form/Task.js'
import { antdLocale } from '../../../../js/app/i18n'
import { ConfigProvider } from 'antd'
import moment from 'moment'

import Map from '../../../../js/app/components/delivery-form/Map.js'
import Spinner from '../../../../js/app/components/core/Spinner.js'
import _ from 'lodash'


import { PhoneNumberUtil } from 'google-libphonenumber'
import { getCountry } from '../../../../js/app/i18n'
import { useTranslation } from 'react-i18next'
import { parsePhoneNumberFromString } from 'libphonenumber-js'


import "./DeliveryForm.scss"
import BarcodesModal from '../BarcodesModal.jsx'
import ShowPrice from '../../../../js/app/components/delivery-form/ShowPrice.js'

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
  weight: 0,
  tags: [],
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
  tags: [],
}


const baseURL = location.protocol + '//' + location.host

export default function ({ storeId, deliveryId, order }) {

  // This variable is used to test the store role and restrictions. We need to have it passed as prop to make it work. 
  const isAdmin = true

  const httpClient = new window._auth.httpClient()

  const [addresses, setAddresses] = useState([])
  const [storeDeliveryInfos, setStoreDeliveryInfos] = useState({})
  const [calculatedPrice, setCalculatePrice] = useState(0)
  const [error, setError] = useState({ isError: false, errorMessage: ' ' })
  const [priceError, setPriceError] = useState({ isPriceError: false, priceErrorMessage: ' ' })
  const [storePackages, setStorePackages] = useState(null)
  const [tags, setTags] = useState([])
  const [trackingLink, setTrackingLink] = useState('#')
  const [initialValues, setInitialValues] = useState({
    tasks: [
      pickupSchema,
      dropoffSchema,
    ],
  })
  const [isLoading, setIsLoading] = useState(Boolean(deliveryId))
  const [overridePrice, setOverridePrice] = useState(false)

  let deliveryPrice

  if (order) {
    const orderInfos = JSON.parse(order)
    deliveryPrice = {exVAT: +orderInfos.total, VAT: +orderInfos.total - +orderInfos.taxTotal,}
  }


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

  useEffect(() => {
    const deliveryURL = `${baseURL}/api/deliveries/${deliveryId}?groups=barcode,address,delivery`
    const addressesURL = `${baseURL}/api/stores/${storeId}/addresses`
    const storeURL = `${baseURL}/api/stores/${storeId}`
    const packagesURL = `${baseURL}/api/stores/${storeId}/packages`
    const tagsURL = `${baseURL}/api/tags`

    if (deliveryId) {
        Promise.all([
        httpClient.get(deliveryURL),
        httpClient.get(addressesURL),
        httpClient.get(storeURL),
        httpClient.get(packagesURL),
        httpClient.get(tagsURL)
        ]).then(values => {
          const [delivery, addresses, storeInfos, packages, tags] = values

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
            if (task.tags.length > 0) {
              const tags = task.tags.map(tag => tag.name)
              task.tags = tags
            }
          })

          delivery.response.overridePrice = false
          
          setInitialValues(delivery.response)
          setTrackingLink(delivery.response.trackingUrl)
          setAddresses(addresses.response['hydra:member'])
          setStoreDeliveryInfos(storeInfos.response)
          setTags(tags.response['hydra:member'])
          setIsLoading(false)
      })
    } else {
        Promise.all([
        httpClient.get(addressesURL),
        httpClient.get(storeURL),
        httpClient.get(packagesURL), 
        httpClient.get(tagsURL)
        ]).then(values => {
          const [addresses, storeInfos, packages, tags] = values
          
          const storePackages = packages.response['hydra:member']
          if (storePackages.length > 0) {
            setStorePackages(storePackages)
          }

          setAddresses(addresses.response['hydra:member'])
          setStoreDeliveryInfos(storeInfos.response)
          setTags(tags.response['hydra:member'])
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
                if(!overridePrice && !deliveryId) getPrice(values)
            }, [values, overridePrice, deliveryId]);

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
                                    tags={tags}
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
                                    isAdmin={isAdmin}
                                    tags={tags}
                                  />
                                </div>
                              );
                            })}
                        </div>
                      </div>
                    )}
                  </FieldArray>

                  <div className="order-informations">

                    {deliveryId && (
                  
                      <div className="order-informations__tracking alert alert-info">
                        <a target="_blank" rel="noreferrer" href={trackingLink}>
                         {t("DELIVERY_FORM_TRACKING_LINK")}
                        </a>{'  '}
                        <i className="fa fa-external-link"></i>
                        <a href="#" className="pull-right"><i className="fa fa-clipboard" title={t("DELIVERY_FROM_TRACKING_LINK_COPY")} aria-hidden="true" onClick={() => navigator.clipboard.writeText(trackingLink)}></i></a>
                        <div className='mt-2'><BarcodesModal deliveryId={deliveryId} /></div>
                      </div>
                      
                    )}

                    <div className="order-informations__map">
                      <Map
                        storeDeliveryInfos={storeDeliveryInfos}
                        tasks={values.tasks}
                      />
                    </div>

                    <div className='order-informations__total-price border-top border-bottom pt-3 pb-3 mb-4'>
                      <ShowPrice
                        deliveryId={deliveryId}
                        deliveryPrice={deliveryPrice}
                        calculatedPrice={calculatedPrice}
                        setCalculatePrice={setCalculatePrice}
                        priceError={priceError}
                        setOverridePrice={setOverridePrice}
                        overridePrice={overridePrice}
                      />
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
