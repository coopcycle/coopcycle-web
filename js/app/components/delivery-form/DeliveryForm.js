import React, { useEffect, useMemo, useState } from 'react'
import { Button, Checkbox } from 'antd'
import { Formik, Form, FieldArray } from 'formik'
import moment from 'moment'

import Spinner from '../../components/core/Spinner.js'
import BarcodesModal from '../../../../assets/react/controllers/BarcodesModal.jsx'
import Task from '../../components/delivery-form/Task.js'
import { usePrevious } from '../../dashboard/redux/utils'

import { PhoneNumberUtil } from 'google-libphonenumber'
import { getCountry } from '../../i18n'
import { useTranslation } from 'react-i18next'
import { parsePhoneNumberFromString } from 'libphonenumber-js'

import "./DeliveryForm.scss"

import {
  useGetStoreAddressesQuery,
  useGetStoreQuery,
  useGetTagsQuery,
} from '../../api/slice'
import { RecurrenceRules } from './RecurrenceRules'
import useSubmit from './hooks/useSubmit'
import Price from './Price'
import SuggestionModal from './SuggestionModal'
import DeliveryResume from './DeliveryResume'
import Map from '../DeliveryMap'

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

export default function({
  storeNodeId,
  deliveryId, // prefer using deliveryNodeId
  deliveryNodeId,
  preLoadedDeliveryData,
  order,
  isDispatcher,
  isDebugPricing
}) {
  const isCreateOrderMode = useMemo(() => {
    return !Boolean(deliveryNodeId)
  }, [deliveryNodeId])

  const isModifyOrderMode = useMemo(() => {
    return !isCreateOrderMode
  }, [isCreateOrderMode])

  const [isLoading, setIsLoading] = useState(true)

  const { data: storeData } = useGetStoreQuery(storeNodeId)
  const storeDeliveryInfos = useMemo(() => storeData ?? {}, [storeData])

  const { data: tagsData } = useGetTagsQuery(undefined, {
    skip: !isDispatcher,
  })
  const { data: addressesData } = useGetStoreAddressesQuery(storeNodeId)

  const tags = useMemo(() => {
    if (tagsData) {
      return tagsData['hydra:member']
    }
    return []
  }, [tagsData])

  const addresses = useMemo(() => {
    if (addressesData) {
      return addressesData['hydra:member']
    }
    return []
  }, [addressesData])

  const [trackingLink, setTrackingLink] = useState('#')
  const [initialValues, setInitialValues] = useState({ tasks: [] })

  const [priceLoading, setPriceLoading] = useState(false)

  const { handleSubmit, error } = useSubmit(storeNodeId, deliveryNodeId, isDispatcher, isCreateOrderMode)

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
        taskErrors.address = taskErrors.address || {};
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

  const isDataReady = useMemo(() => {
    if (!storeData) return false

    if (!addressesData) return false

    if (isDispatcher && !tagsData) {
      return false
    }

    return true
  }, [
    storeData,
    addressesData,
    tagsData,
    isDispatcher
  ])

  useEffect(() => {
    if (!isDataReady) return

    if (preLoadedDeliveryData) {
      const initialValues = structuredClone(preLoadedDeliveryData)

      initialValues.tasks = preLoadedDeliveryData.tasks.map(task => {
        return {
          ...task,
          address: {
            ...task.address,
            formattedTelephone: getFormattedValue(task.address.telephone),
          },
        }
      })

      if (preLoadedDeliveryData.order?.arbitraryPrice) {
        // remove a previously copied value (different formats between API and the frontend)
        delete initialValues.order.arbitraryPrice

        initialValues.variantName =
          preLoadedDeliveryData.order.arbitraryPrice.variantName
        initialValues.variantIncVATPrice =
          preLoadedDeliveryData.order.arbitraryPrice.variantPrice
      }

      setInitialValues(initialValues)

      setTrackingLink(preLoadedDeliveryData.trackingUrl)
    } else {
      if (isCreateOrderMode) {
        setInitialValues({
          tasks: [{ ...pickupSchema }, { ...dropoffSchema }],
          order: {},
        })
      }
    }

    setIsLoading(false)
  }, [isDataReady, preLoadedDeliveryData, isCreateOrderMode, isModifyOrderMode])

  return (
    isLoading ?
      <div className="delivery-spinner">
        <Spinner />
      </div>
      :
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
              if (prevFirstTask.timeSlot && firstTask.timeSlot && prevFirstTask.timeSlot !== firstTask.timeSlot) {
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
              }

            }, [values, previousValues, setFieldValue]);

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
                                    isEditMode={isModifyOrderMode}
                                    key={originalIndex}
                                    task={task}
                                    index={originalIndex}
                                    addresses={addresses}
                                    storeNodeId={storeNodeId}
                                    storeDeliveryInfos={storeDeliveryInfos}
                                    isDispatcher={isDispatcher}
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
                                    isEditMode={isModifyOrderMode}
                                    index={originalIndex}
                                    addresses={addresses}
                                    storeNodeId={storeNodeId}
                                    storeDeliveryInfos={storeDeliveryInfos}
                                    onRemove={arrayHelpers.remove}
                                    showRemoveButton={originalIndex > 1}
                                    isDispatcher={isDispatcher}
                                    tags={tags}
                                  />
                                </div>
                              );
                            })}

                          {storeDeliveryInfos.multiDropEnabled && (isCreateOrderMode || isDispatcher) ? <div
                            className="new-order__dropoffs__add p-4 border mb-4">
                            <p>{t('DELIVERY_FORM_MULTIDROPOFF')}</p>
                            <Button
                              data-testid="add-dropoff-button"
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
                    {isModifyOrderMode && (
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
                      <Map defaultAddress={storeDeliveryInfos.address} tasks={values.tasks} />
                      <DeliveryResume tasks={values.tasks} />
                    </div>

                    <div className="order-informations__total-price border-top py-3">
                      <Price
                        storeNodeId={storeNodeId}
                        order={order}
                        isDispatcher={isDispatcher}
                        isDebugPricing={isDebugPricing}
                        setPriceLoading={setPriceLoading}
                      />
                    </div>

                    {isCreateOrderMode && isDispatcher ? (
                      <div className="border-top pt-2 pb-3" data-testid="recurrence__container">
                        <RecurrenceRules />
                      </div>
                    ) : null}

                    {isDispatcher ? (
                      <div className="border-top py-3" data-testid="saved_order__container">
                        <Checkbox
                          name="delivery.saved_order"
                          checked={values.order.isSavedOrder}
                          onChange={e => {
                            e.stopPropagation()
                            setFieldValue('order.isSavedOrder', e.target.checked)
                          }}>{t('DELIVERY_FORM_SAVED_ORDER')}</Checkbox>
                      </div>
                    ) : null}

                    {isCreateOrderMode || isDispatcher ? (
                      <div className="order-informations__complete-order border-top py-3">
                        <SuggestionModal />
                        <Button
                          type="primary"
                          style={{ height: '2.5em' }}
                          htmlType="submit"
                          disabled={isSubmitting || priceLoading}>
                          {t('DELIVERY_FORM_SUBMIT')}
                        </Button>
                      </div>
                    ) : null}

                    {error.isError ? (
                      <div className="border-top py-3">
                        <div className="alert alert-danger" role="alert">
                          {error.errorMessage}
                        </div>
                      </div>
                    ) : null}
                  </div>
                </div>
              </Form>
            )
          }}
        </Formik>
  )
}
