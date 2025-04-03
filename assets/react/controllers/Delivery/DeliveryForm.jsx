import React, { useCallback, useEffect, useState } from 'react'
import { Button } from 'antd'
import { Formik, Form, FieldArray } from 'formik'
import { antdLocale } from '../../../../js/app/i18n'
import { ConfigProvider } from 'antd'
import moment from 'moment'

import Map from '../../../../js/app/components/delivery-form/Map.js'
import Spinner from '../../../../js/app/components/core/Spinner.js'
import BarcodesModal from '../BarcodesModal.jsx'
import ShowPrice from '../../../../js/app/components/delivery-form/ShowPrice.js'
import Task from '../../../../js/app/components/delivery-form/Task.js'
import { usePrevious } from '../../../../js/app/dashboard/redux/utils'

import { PhoneNumberUtil } from 'google-libphonenumber'
import { getCountry } from '../../../../js/app/i18n'
import { useTranslation } from 'react-i18next'
import { parsePhoneNumberFromString } from 'libphonenumber-js'


import "./DeliveryForm.scss"
import _ from 'lodash'
import { PriceCalculation } from '../../../../js/app/delivery/PriceCalculation'


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

/** TODO : use this validation when we port the form for store owners for which the phone is required */
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
  before: getNextRoundedTime().add(10, 'minutes').toISOString(),
  timeSlot: null,
  timeSlotUrl: null,
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
  before: getNextRoundedTime().add(10, 'minutes').toISOString(),
  timeSlot: null,
  timeSlotUrl: null,
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

export default function({ storeId, deliveryId, order, isDispatcher, isDebugPricing }) {

  const httpClient = new window._auth.httpClient()

  const [addresses, setAddresses] = useState([])
  const [storeDeliveryInfos, setStoreDeliveryInfos] = useState({})
  const [calculatedPrice, setCalculatePrice] = useState(0)
  const [error, setError] = useState({ isError: false, errorMessage: ' ' })
  const [priceErrorMessage, setPriceErrorMessage] = useState('')
  const [storePackages, setStorePackages] = useState(null)
  const [tags, setTags] = useState([])
  const [timeSlotLabels, setTimeSlotLabels] = useState([])
  const [trackingLink, setTrackingLink] = useState('#')
  const [initialValues, setInitialValues] = useState({tasks: []})
  const [isLoading, setIsLoading] = useState(true)
  const [overridePrice, setOverridePrice] = useState(false)
  const [priceLoading, setPriceLoading] = useState(false)


  let deliveryPrice

  if (deliveryId && order) {
    const orderInfos = JSON.parse(order)
    deliveryPrice = { exVAT: +orderInfos.total, VAT: +orderInfos.total - +orderInfos.taxTotal, }
  }

  const { t } = useTranslation()

  const validate = (values) => {
    const errors = { tasks: [] };

    for (let i = 0; i < values.tasks.length; i++) {

      const taskErrors = {}

      if (!isDispatcher) {
        if (!values.tasks[i].address.formattedTelephone) {
          taskErrors.address = taskErrors.address || {};
          taskErrors.address.formattedTelephone = t("FORM_REQUIRED")
        }

        if (!values.tasks[i].address.contactName) {
          taskErrors.address = taskErrors.address || {};
          taskErrors.address.contactName = t("FORM_REQUIRED")
        }

        if (!values.tasks[i].address.name) {
          taskErrors.address = taskErrors.address || {};
          taskErrors.address.name = t("FORM_REQUIRED")
        }
      }

      if (!validatePhoneNumber(values.tasks[i].address.formattedTelephone)) {
        taskErrors.address.formattedTelephone = t("ADMIN_DASHBOARD_TASK_FORM_TELEPHONE_ERROR")
      }

      if (values.tasks[i].type === 'DROPOFF' && storeDeliveryInfos.packagesRequired && !values.tasks[i].packages.some(item => item.quantity > 0)) {
        taskErrors.packages = t("DELIVERY_FORM_ERROR_PACKAGES")
      }

      if (values.tasks[i].type === "DROPOFF" && storeDeliveryInfos.weightRequired && !values.tasks[i].weight) {
        taskErrors.weight = t("DELIVERY_FORM_ERROR_WEIGHT")
      }

      if (Object.keys(taskErrors).length > 0) {
        errors.tasks[i] = taskErrors
      }
    }

    return Object.keys(errors.tasks).length > 0 || errors.variantName ? errors : {};
  }

  useEffect(() => {
    const fetchTags = () => new Promise(resolve => {
      httpClient.get(`/api/tags`).then(result => {
        setTags(result.response['hydra:member'])
        resolve()
      })      
    })

    const fetchAddresses = () => new Promise(resolve => {
      httpClient.get(`/api/stores/${storeId}/addresses`).then(result => {
        setAddresses(result.response['hydra:member'])
        resolve()
      })      
    })

    const fetchTimeSlots = () => new Promise(resolve => {
      httpClient.get(`/api/stores/${storeId}/time_slots`).then(result => {
        setTimeSlotLabels(result.response['hydra:member'])
        resolve()
      })      
    })

    const fetchPackages = () => new Promise(resolve => {
      httpClient.get(`/api/stores/${storeId}/packages`).then(result => {
        setStorePackages(result.response['hydra:member'])
        resolve()
      })
    })

    const fetchStoreDeliveryInfos = () => new Promise(resolve => {
      httpClient.get(`/api/stores/${storeId}`).then(result => {
        setStoreDeliveryInfos(result.response)

        if (!deliveryId) {
          setInitialValues({
            tasks: [
              {...pickupSchema, timeSlotUrl: result.response.timeSlot},
              {...dropoffSchema, timeSlotUrl: result.response.timeSlot}
            ],
          })
        }
        resolve()
      })
    })

    const fetchDeliveryInfos = () => new Promise(resolve => {
      if (deliveryId) {
        httpClient.get(`/api/deliveries/${deliveryId}?groups=barcode,address,delivery`).then(result => {
          let response = result.response

          //we delete duplication of data as we only modify tasks to avoid potential conflicts/confusions
          delete response.dropoff
          delete response.pickup
  
          response.tasks.forEach(task => {
            const formattedTelephone = getFormattedValue(task.address.telephone)
            task.address.formattedTelephone = formattedTelephone
          })
          setInitialValues(response)
          setTrackingLink(response.trackingUrl)
          resolve()
        })
      }
    })

    const promises = [fetchAddresses(), fetchPackages(), fetchTimeSlots(), fetchStoreDeliveryInfos()]

    if (isDispatcher) {
      promises.push(fetchTags())
    }

    if (deliveryId) {
      promises.push(fetchDeliveryInfos())
    }

    Promise.all(promises).then(() => setIsLoading(false))
  }, [])

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
      deliveryId && !isDispatcher
      let data = {
        store: storeDeliveryInfos['@id'],
        tasks: values.tasks
      }

      if (values.variantIncVATPrice && values.variantName) {
        data = {
          ...data,
          arbitraryPrice: {
            variantPrice: values.variantIncVATPrice,
            variantName: values.variantName
          }
        }
      }

      return await httpClient[method](url, data);
    }

    const { response, error } = await createOrEditADelivery(deliveryId)

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
      window.location = isDispatcher ? "/admin/deliveries" : `/dashboard/stores/${storeId}/deliveries`
    }
  }, [storeDeliveryInfos])

  const isStoreOwnerAndEdit = deliveryId && !isDispatcher

  const getPrice = _.debounce(
    (values) => {

      const tasksCopy = structuredClone(values.tasks)
      const tasksWithoutId = tasksCopy.map(task => {
        if (task["@id"]) {
          delete task["@id"]
        }
        return task
      })

      const infos = {
        store: storeDeliveryInfos["@id"],
        tasks: tasksWithoutId,
      };

      const calculatePrice = async () => {

        setPriceLoading(true)

        const url = `${baseURL}/api/retail_prices/calculate`
        const { response, error } = await httpClient.post(url, infos)

        if (error) {
          setPriceErrorMessage(error.response.data['hydra:description'])
          setCalculatePrice(0)
        }

        if (response) {
          setCalculatePrice(response)
          setPriceErrorMessage('')

        }

        setPriceLoading(false)

      }

      if (values.tasks.every(task => task.address.streetAddress)) {
        calculatePrice()
      }

    },
    800
  )
  
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
          {({ values, isSubmitting, setFieldValue }) => {

            const previousValues = usePrevious(values)

            useEffect(() => {
              if (!overridePrice && !deliveryId) {
                getPrice(values)
              }
            }, [values.tasks, overridePrice, deliveryId]);

            useEffect(() => {

              // Skip if no or 1 task
              if (!values.tasks || values.tasks.length <= 1) return;
              const firstTask = values.tasks[0]
              const prevFirstTask = previousValues?.tasks?.[0]
              // Skip if no previous tasks
              if (!prevFirstTask) return;

              const newPickupAfter = firstTask.after
              const hasAfterChanged = prevFirstTask.after !== newPickupAfter
              const isOnSameDay = moment(newPickupAfter)
                .isSame(moment(prevFirstTask.before), 'day')

              // Case 1: "after" time changed and is on the same day as "before"
              if (hasAfterChanged && isOnSameDay) {
                values.tasks.slice(1).forEach((task, idx) => {
                  const taskIndex = idx + 1;
                  if (moment(newPickupAfter).isAfter(moment(task.after))) {
                    setFieldValue(`tasks[${taskIndex}].after`, firstTask.after);
                    setFieldValue(`tasks[${taskIndex}].before`, firstTask.before);
                  }
                });
                return;
              }

              // Case 2: Time slot changed
              if (prevFirstTask.timeSlotUrl && firstTask.timeSlotUrl && prevFirstTask.timeSlotUrl !== firstTask.timeSlotUrl) {
                values.tasks.slice(1).forEach((_, idx) => {
                  const taskIndex = idx + 1;
                  setFieldValue(`tasks[${taskIndex}].timeSlotUrl`, firstTask.timeSlotUrl);
                });
                return;
              }

              //Case 3: Time slot value changed
              if (prevFirstTask.timeSlot &&firstTask.timeSlot && prevFirstTask.timeSlot !== firstTask.timeSlot) {
                values.tasks.slice(1).forEach((task, idx) => {
                  const taskIndex = idx + 1;
                  if (task.timeSlot) {
                    const pickupAfter = moment(firstTask.timeSlot.split('/')[0]);
                    const dropAfter = moment(task.timeSlot.split('/')[0]);

                    if (pickupAfter.isAfter(dropAfter)) {
                      setFieldValue(`tasks[${taskIndex}].timeSlot`, firstTask.timeSlot);
                    }
                  }
                });
                return;
              }


            }, [values.tasks, overridePrice, deliveryId]);

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
                                    isEdit={Boolean(deliveryId)}
                                    key={originalIndex}
                                    task={task}
                                    index={originalIndex}
                                    addresses={addresses}
                                    storeId={storeId}
                                    storeDeliveryInfos={storeDeliveryInfos}
                                    packages={storePackages}
                                    isDispatcher={isDispatcher}
                                    tags={tags}
                                    timeSlotLabels={timeSlotLabels}
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
                                    isEdit={Boolean(deliveryId)}
                                    index={originalIndex}
                                    addresses={addresses}
                                    storeId={storeId}
                                    storeDeliveryInfos={storeDeliveryInfos}
                                    onRemove={arrayHelpers.remove}
                                    showRemoveButton={originalIndex > 1}
                                    packages={storePackages}
                                    isDispatcher={isDispatcher}
                                    tags={tags}
                                    timeSlotLabels={timeSlotLabels}
                                  />
                                </div>
                              );
                            })}

                          {storeDeliveryInfos.multiDropEnabled && !(isStoreOwnerAndEdit) ? <div
                            className="new-order__dropoffs__add p-4 border mb-4">
                            <p>{t('DELIVERY_FORM_MULTIDROPOFF')}</p>
                            <Button
                              disabled={false}
                              onClick={() => {
                                const newDeliverySchema = {
                                  ...dropoffSchema,
                                  before: values.tasks.slice(-1)[0].before,
                                  after: values.tasks.slice(-1)[0].after,
                                  timeSlot: values.tasks.slice(-1)[0].timeSlot,
                                  timeSlotUrl: values.tasks.slice(-1)[0].timeSlotUrl
                                }
                                arrayHelpers.push(newDeliverySchema)
                              }}>
                              {t('DELIVERY_FORM_ADD_DROPOFF')}
                            </Button>
                          </div> : null}
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

                    <div className='order-informations__total-price border-top border-bottom pt-3 mb-4'>
                      <ShowPrice
                        isDispatcher={isDispatcher}
                        deliveryId={deliveryId}
                        deliveryPrice={deliveryPrice}
                        calculatedPrice={calculatedPrice}
                        setCalculatePrice={setCalculatePrice}
                        priceErrorMessage={priceErrorMessage}
                        setOverridePrice={setOverridePrice}
                        overridePrice={overridePrice}
                        priceLoading={priceLoading}
                      />
                      { isDispatcher && isDebugPricing && Boolean(calculatedPrice) && (
                        <PriceCalculation
                          calculation={calculatedPrice.calculation}
                          orderItems={calculatedPrice.items}
                          itemsTotal={calculatedPrice.amount} />
                      )}
                    </div>

                    {!(deliveryId && !isDispatcher) ?
                      <div className='order-informations__complete-order'>
                        <Button
                          type="primary"
                          style={{ height: '2.5em' }}
                          htmlType="submit"
                          disabled={isSubmitting || priceLoading}>
                          {t("DELIVERY_FORM_SUBMIT")}
                        </Button>
                      </div> : null
                    }

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
