import React, { useContext, useEffect, useMemo, useState } from 'react'
import { Button, Checkbox } from 'antd'
import { Formik, Form, FieldArray, FormikErrors } from 'formik'
import moment, { Moment } from 'moment'
import { v4 as uuidv4 } from 'uuid'

import Spinner from '../../components/core/Spinner.js'
import BarcodesModal from '../../../../assets/react/controllers/BarcodesModal.jsx'
import Task from './components/task/Task'
import { usePrevious } from '../../dashboard/redux/utils'

import { PhoneNumberUtil } from 'google-libphonenumber'
import { getCountry } from '../../i18n'
import { useTranslation } from 'react-i18next'
import { parsePhoneNumberFromString } from 'libphonenumber-js'

import './DeliveryForm.scss'

import {
  useGetStoreAddressesQuery,
  useGetStoreQuery,
  useGetTagsQuery,
} from '../../api/slice'
import { RecurrenceRules } from './components/recurrence/RecurrenceRules'
import useSubmit from './hooks/useSubmit'
import Order from './components/order/Order'
import SuggestionModal from './SuggestionModal'
import DeliveryResume from './DeliveryResume'
import Map from '../DeliveryMap'
import { Mode, modeIn } from './mode'
import { useSelector } from 'react-redux'
import { selectMode } from './redux/formSlice'
import FlagsContext from './FlagsContext'
import type { DeliveryFormValues } from './types'
import {
  Uri,
  PutDeliveryRequest,
  Store,
  Task as TaskType,
  TaskPayload,
} from '../../api/types'
import { useDatadog } from '../../hooks/useDatadog'

const generateTempId = (): string => `temp-${uuidv4()}`

const getTaskId = (task: TaskType): string | null => {
  return task['@id']
}

/** used in case of phone validation */
const phoneUtil = PhoneNumberUtil.getInstance()

const getNextRoundedTime = (): Moment => {
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
const validatePhoneNumber = (telephone: string | null): boolean => {
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
}

function getFormattedValue(value: string | null): string {
  if (typeof value === 'string') {
    const phoneNumber = parsePhoneNumberFromString(
      value,
      (getCountry() || 'fr').toUpperCase(),
    )
    return phoneNumber ? phoneNumber.formatNational() : value
  }
  return value || ''
}

function canAddAnother(
  type: 'PICKUP' | 'DROPOFF',
  pickups: TaskType[],
  dropoffs: TaskType[],
): boolean {
  switch (type) {
    case 'PICKUP':
      return dropoffs.length === 1
    case 'DROPOFF':
      return pickups.length === 1
  }

  return true
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
  '@id': null, // Will be set when creating new tasks
}

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
    formattedTelephone: null,
  },
  saveInStoreAddresses: false,
  updateInStoreAddresses: false,
  tags: [],
  '@id': null, // Will be set when creating new tasks
}

type Props = {
  storeNodeId: Uri
  deliveryId?: number
  deliveryNodeId?: Uri
  preLoadedDeliveryData?: PutDeliveryRequest | null
}

const DeliveryForm = ({
  storeNodeId,
  // prefer using deliveryNodeId
  deliveryId,
  // nodeId: Delivery or RecurrenceRule node
  deliveryNodeId,
  preLoadedDeliveryData,
}: Props) => {
  const { isDispatcher } = useContext(FlagsContext)

  const mode = useSelector(selectMode)
  const [isLoading, setIsLoading] = useState<boolean>(true)
  const [expandedTasks, setExpandedTasks] = useState<Record<number, boolean>>(
    {},
  )

  const { data: storeData } = useGetStoreQuery(storeNodeId)
  const storeDeliveryInfos = useMemo(
    () => storeData ?? ({} as Partial<Store>),
    [storeData],
  )

  const { data: tags } = useGetTagsQuery(undefined, {
    skip: !isDispatcher,
  })
  const { data: addresses } = useGetStoreAddressesQuery(storeNodeId)

  const [trackingLink, setTrackingLink] = useState<string>('#')
  const [initialValues, setInitialValues] = useState<DeliveryFormValues>({
    tasks: [],
  })

  const [priceLoading, setPriceLoading] = useState<boolean>(false)

  const order = useMemo(() => {
    if (mode === Mode.DELIVERY_CREATE) {
      if (preLoadedDeliveryData && preLoadedDeliveryData.order) {
        return preLoadedDeliveryData.order
      }

      return {
        total: 0,
        taxTotal: 0,
        isSavedOrder: false,
      }
    }

    if (mode === Mode.DELIVERY_UPDATE) {
      if (preLoadedDeliveryData.order?.id) {
        return preLoadedDeliveryData.order
      } else {
        // A case where the delivery is not linked to an order
        return null
      }
    }

    return null
  }, [preLoadedDeliveryData, mode])

  const { handleSubmit, error, isSubmitted } = useSubmit(
    storeNodeId,
    deliveryNodeId,
    isDispatcher,
  )

  const { t } = useTranslation()

  const { logger } = useDatadog()

  const handleTaskExpansion = (taskIndex: number, isExpanded: boolean) => {
    setExpandedTasks(prev => ({
      ...prev,
      [taskIndex]: isExpanded,
    }))
  }

  const validate = (
    values: DeliveryFormValues,
  ): FormikErrors<DeliveryFormValues> => {
    const errors: FormikErrors<DeliveryFormValues> = { tasks: [] }

    for (let i = 0; i < values.tasks.length; i++) {
      const taskErrors: FormikErrors<TaskPayload> = {}

      if (!isDispatcher) {
        if (!values.tasks[i].address.formattedTelephone) {
          taskErrors.address = taskErrors.address || {}
          taskErrors.address.formattedTelephone = t('FORM_REQUIRED')
        }

        if (!values.tasks[i].address.contactName) {
          taskErrors.address = taskErrors.address || {}
          taskErrors.address.contactName = t('FORM_REQUIRED')
        }

        if (!values.tasks[i].address.name) {
          taskErrors.address = taskErrors.address || {}
          taskErrors.address.name = t('FORM_REQUIRED')
        }
      }

      if (!validatePhoneNumber(values.tasks[i].address.formattedTelephone)) {
        taskErrors.address = taskErrors.address || {}
        taskErrors.address.formattedTelephone = t(
          'ADMIN_DASHBOARD_TASK_FORM_TELEPHONE_ERROR',
        )
      }

      if (
        values.tasks[i].type === 'DROPOFF' &&
        storeDeliveryInfos.packagesRequired &&
        !values.tasks[i].packages.some(item => item.quantity > 0)
      ) {
        taskErrors.packages = t('DELIVERY_FORM_ERROR_PACKAGES')
      }

      if (
        values.tasks[i].type === 'DROPOFF' &&
        storeDeliveryInfos.weightRequired &&
        !values.tasks[i].weight
      ) {
        taskErrors.weight = t('DELIVERY_FORM_ERROR_WEIGHT')
      }

      if (Object.keys(taskErrors).length > 0) {
        errors.tasks[i] = taskErrors
      }
    }

    // expand all tasks with errors
    if (Object.keys(errors.tasks).length > 0) {
      Object.values(expandedTasks).forEach((isExpanded, index) => {
        if (!isExpanded && Object.keys(errors.tasks).includes(`${index}`)) {
          handleTaskExpansion(index, true)
        }
      })
    }

    const result =
      Object.keys(errors.tasks).length > 0 || errors.variantName ? errors : {}

    if (Object.keys(result).length > 0) {
      logger.warn('Delivery form validation error', result)
    }

    return result
  }

  const isDataReady = useMemo(() => {
    if (!storeData) return false

    if (!addresses) return false

    if (isDispatcher && !tags) {
      return false
    }

    return true
  }, [storeData, addresses, tags, isDispatcher])

  useEffect(() => {
    if (!isDataReady) return

    const initialExpandedState = {}
    if (preLoadedDeliveryData) {
      const initialValues = structuredClone(
        preLoadedDeliveryData,
      ) as DeliveryFormValues

      initialValues.tasks = preLoadedDeliveryData.tasks.map(task => {
        return {
          ...task,
          // Ensure each task has an @id (use existing or generate temporary)
          '@id': task['@id'] || generateTempId(),
          address: {
            ...task.address,
            formattedTelephone: getFormattedValue(task.address.telephone),
          },
        }
      })

      if (!initialValues.order) {
        initialValues.order = {}
      }

      // Ensure order.manualSupplements is initialized
      if (!initialValues.order.manualSupplements) {
        initialValues.order.manualSupplements = []
      }

      if (preLoadedDeliveryData.order?.arbitraryPrice) {
        // remove a previously copied value (different formats between API and the frontend)
        delete initialValues.order.arbitraryPrice

        initialValues.variantName =
          preLoadedDeliveryData.order.arbitraryPrice.variantName
        initialValues.variantIncVATPrice =
          preLoadedDeliveryData.order.arbitraryPrice.variantPrice
      }

      setInitialValues(initialValues)

      // For simple deliveries, expand all tasks by default
      if (initialValues.tasks.length <= 2) {
        initialValues.tasks.forEach((_, index) => {
          initialExpandedState[index] = true
        })
        // For complex deliveries, collapse all tasks by default
      } else {
        initialValues.tasks.forEach((_, index) => {
          initialExpandedState[index] = false
        })
      }

      setTrackingLink(preLoadedDeliveryData.trackingUrl)
    } else {
      if (mode === Mode.DELIVERY_CREATE) {
        const tasks = [
          { ...pickupSchema, '@id': generateTempId() },
          { ...dropoffSchema, '@id': generateTempId() },
        ]

        setInitialValues({
          tasks: tasks,
          order: {
            manualSupplements: [],
          },
        })

        // For new deliveries - expand all tasks by default
        tasks.forEach((task, index) => {
          initialExpandedState[index] = true
        })
      }
    }

    setExpandedTasks(initialExpandedState)

    setIsLoading(false)
  }, [isDataReady, preLoadedDeliveryData, mode])

  return isLoading ? (
    <div className="delivery-spinner">
      <Spinner />
    </div>
  ) : (
    <Formik
      initialValues={initialValues}
      onSubmit={handleSubmit}
      validate={validate}
      validateOnChange={false}
      validateOnBlur={false}>
      {({ values, isSubmitting, setFieldValue }) => {
        //FIXME: we probably need to move all this into a function component
        // eslint-disable-next-line react-hooks/rules-of-hooks
        const previousValues = usePrevious(values)

        //FIXME: we probably need to move all this into a function component
        // eslint-disable-next-line react-hooks/rules-of-hooks
        useEffect(() => {
          // Skip if no or 1 task
          if (!values.tasks || values.tasks.length <= 1) return

          const firstTask = values.tasks[0]
          const prevFirstTask = previousValues?.tasks?.[0]

          // Skip if no previous tasks
          if (!prevFirstTask) return

          const newPickupAfter = firstTask.after
          const hasAfterChanged = prevFirstTask.after !== newPickupAfter
          const isOnSameDay = moment(newPickupAfter).isSame(
            moment(prevFirstTask.before),
            'day',
          )

          // Case 1: "after" time changed and is on the same day as "before"
          if (hasAfterChanged && isOnSameDay) {
            values.tasks.slice(1).forEach((task, idx) => {
              const taskIndex = idx + 1
              if (moment(newPickupAfter).isAfter(moment(task.after))) {
                setFieldValue(`tasks[${taskIndex}].after`, firstTask.after)
                setFieldValue(`tasks[${taskIndex}].before`, firstTask.before)
              }
            })
            return
          }

          // Case 2: Time slot changed
          if (
            prevFirstTask.timeSlotUrl &&
            firstTask.timeSlotUrl &&
            prevFirstTask.timeSlotUrl !== firstTask.timeSlotUrl
          ) {
            values.tasks.slice(1).forEach((_, idx) => {
              const taskIndex = idx + 1
              setFieldValue(
                `tasks[${taskIndex}].timeSlotUrl`,
                firstTask.timeSlotUrl,
              )
            })
            return
          }

          //Case 3: Time slot value changed
          if (
            prevFirstTask.timeSlot &&
            firstTask.timeSlot &&
            prevFirstTask.timeSlot !== firstTask.timeSlot
          ) {
            values.tasks.slice(1).forEach((task, idx) => {
              const taskIndex = idx + 1
              if (task.timeSlot) {
                const pickupAfter = moment(firstTask.timeSlot.split('/')[0])
                const dropAfter = moment(task.timeSlot.split('/')[0])

                if (pickupAfter.isAfter(dropAfter)) {
                  setFieldValue(
                    `tasks[${taskIndex}].timeSlot`,
                    firstTask.timeSlot,
                  )
                }
              }
            })
          }
        }, [values, previousValues, setFieldValue])

        const pickups = values.tasks.filter(task => task.type === 'PICKUP')
        const dropoffs = values.tasks.filter(task => task.type === 'DROPOFF')

        return (
          <Form>
            <div className="delivery-form">
              <FieldArray name="tasks">
                {arrayHelpers => (
                  <div className="new-order">
                    <div className="new-order__pickups">
                      {pickups.map(task => {
                        const originalIndex = values.tasks.findIndex(
                          t => t === task,
                        )
                        const pickupIndex = pickups.findIndex(t => t === task)
                        return (
                          <div
                            className="new-order__pickups__item"
                            key={originalIndex}>
                            <Task
                              key={originalIndex}
                              task={task}
                              taskId={getTaskId(task)}
                              addresses={addresses}
                              storeNodeId={storeNodeId}
                              storeDeliveryInfos={storeDeliveryInfos}
                              onRemove={arrayHelpers.remove}
                              showRemoveButton={pickupIndex > 0}
                              isDispatcher={isDispatcher}
                              tags={tags}
                              isExpanded={expandedTasks[originalIndex]}
                              onToggleExpanded={isExpanded =>
                                handleTaskExpansion(originalIndex, isExpanded)
                              }
                              // Show packages on pickups conditionally
                              showPackages={
                                storeDeliveryInfos.multiPickupEnabled
                              }
                            />
                          </div>
                        )
                      })}

                      {storeDeliveryInfos.multiPickupEnabled &&
                      (mode === Mode.DELIVERY_CREATE || isDispatcher) ? (
                        <div className="new-order__pickups__add p-4 border mb-4">
                          <p>{t('DELIVERY_FORM_MULTIPICKUP')}</p>
                          <Button
                            data-testid="add-pickup-button"
                            disabled={
                              !canAddAnother('PICKUP', pickups, dropoffs)
                            }
                            onClick={() => {
                              const newTaskId = generateTempId()
                              const newDeliverySchema = {
                                ...pickupSchema,
                                '@id': newTaskId,
                                before: values.tasks.slice(-1)[0].before,
                                after: values.tasks.slice(-1)[0].after,
                                timeSlot: values.tasks.slice(-1)[0].timeSlot,
                                timeSlotUrl:
                                  values.tasks.slice(-1)[0].timeSlotUrl,
                              }
                              // Insert after the last pickup using pickups.length
                              arrayHelpers.insert(
                                pickups.length,
                                newDeliverySchema,
                              )

                              // Auto-expand the newly added task and collapse all previous tasks
                              const newTaskIndex = pickups.length // Index of the new task after it's added
                              const totalTasks = values.tasks.length + 1

                              const newExpandedState = {}
                              for (let i = 0; i < totalTasks; i++) {
                                newExpandedState[i] = i === newTaskIndex
                              }
                              setExpandedTasks(newExpandedState)
                            }}>
                            {t('DELIVERY_FORM_ADD_PICKUP')}
                          </Button>
                        </div>
                      ) : null}
                    </div>

                    <div
                      className="new-order__dropoffs"
                      style={{ display: 'flex', flexDirection: 'column' }}>
                      {dropoffs.map(task => {
                        const originalIndex = values.tasks.findIndex(
                          t => t === task,
                        )
                        const dropoffIndex = dropoffs.findIndex(t => t === task)
                        return (
                          <div
                            className="new-order__dropoffs__item"
                            key={originalIndex}>
                            <Task
                              taskId={getTaskId(task)}
                              addresses={addresses}
                              storeNodeId={storeNodeId}
                              storeDeliveryInfos={storeDeliveryInfos}
                              onRemove={arrayHelpers.remove}
                              showRemoveButton={dropoffIndex > 0}
                              isDispatcher={isDispatcher}
                              tags={tags}
                              isExpanded={expandedTasks[originalIndex]}
                              onToggleExpanded={isExpanded =>
                                handleTaskExpansion(originalIndex, isExpanded)
                              }
                              // Always show packages on dropoffs
                              showPackages={true}
                            />
                          </div>
                        )
                      })}

                      {storeDeliveryInfos.multiDropEnabled &&
                      (mode === Mode.DELIVERY_CREATE || isDispatcher) ? (
                        <div className="new-order__dropoffs__add p-4 border mb-4">
                          <p>{t('DELIVERY_FORM_MULTIDROPOFF')}</p>
                          <Button
                            data-testid="add-dropoff-button"
                            disabled={
                              !canAddAnother('DROPOFF', pickups, dropoffs)
                            }
                            onClick={() => {
                              const newTaskId = generateTempId()
                              const newDeliverySchema = {
                                ...dropoffSchema,
                                '@id': newTaskId,
                                before: values.tasks.slice(-1)[0].before,
                                after: values.tasks.slice(-1)[0].after,
                                timeSlot: values.tasks.slice(-1)[0].timeSlot,
                                timeSlotUrl:
                                  values.tasks.slice(-1)[0].timeSlotUrl,
                              }
                              arrayHelpers.push(newDeliverySchema)

                              // Auto-expand the newly added task and collapse all previous tasks
                              const newTaskIndex = values.tasks.length // Index of the new task after it's added
                              const totalTasks = values.tasks.length + 1

                              const newExpandedState = {}
                              for (let i = 0; i < totalTasks; i++) {
                                newExpandedState[i] = i === newTaskIndex
                              }
                              setExpandedTasks(newExpandedState)
                            }}>
                            {t('DELIVERY_FORM_ADD_DROPOFF')}
                          </Button>
                        </div>
                      ) : null}
                    </div>
                  </div>
                )}
              </FieldArray>

              <div className="order-informations">
                {mode === Mode.DELIVERY_UPDATE && (
                  <div className="order-informations__tracking alert alert-info">
                    <a target="_blank" rel="noreferrer" href={trackingLink}>
                      {t('DELIVERY_FORM_TRACKING_LINK')}
                    </a>
                    {'  '}
                    <i className="fa fa-external-link"></i>
                    <a href="#" className="pull-right">
                      <i
                        className="fa fa-clipboard"
                        title={t('DELIVERY_FROM_TRACKING_LINK_COPY')}
                        aria-hidden="true"
                        onClick={() =>
                          navigator.clipboard.writeText(trackingLink)
                        }></i>
                    </a>
                    <div className="mt-2">
                      <BarcodesModal deliveryId={deliveryId} />
                    </div>
                  </div>
                )}

                <div className="order-informations__map">
                  <Map
                    defaultAddress={storeDeliveryInfos.address}
                    tasks={values.tasks}
                  />
                  <DeliveryResume tasks={values.tasks} />
                </div>

                {order || mode === Mode.RECURRENCE_RULE_UPDATE ? (
                  <div className="order-informations__total-price border-top py-3">
                    <Order
                      storeNodeId={storeNodeId}
                      order={order}
                      setPriceLoading={setPriceLoading}
                    />
                  </div>
                ) : null}

                {modeIn(mode, [
                  Mode.DELIVERY_CREATE,
                  Mode.RECURRENCE_RULE_UPDATE,
                ]) && isDispatcher ? (
                  <div
                    className="border-top pt-2 pb-3"
                    data-testid="recurrence-container">
                    <RecurrenceRules />
                  </div>
                ) : null}

                {modeIn(mode, [Mode.DELIVERY_CREATE, Mode.DELIVERY_UPDATE]) &&
                order &&
                isDispatcher ? (
                  <div
                    className="border-top py-3"
                    data-testid="saved_order__container">
                    <Checkbox
                      name="delivery.saved_order"
                      checked={values.order.isSavedOrder}
                      onChange={e => {
                        e.stopPropagation()
                        setFieldValue('order.isSavedOrder', e.target.checked)
                      }}>
                      {t('DELIVERY_FORM_SAVED_ORDER')}
                    </Checkbox>
                  </div>
                ) : null}

                {mode === Mode.DELIVERY_CREATE || isDispatcher ? (
                  <div className="border-top py-3">
                    <SuggestionModal />
                    <Button
                      type="primary"
                      style={{ height: '2.5em' }}
                      htmlType="submit"
                      disabled={isSubmitting || priceLoading || isSubmitted}>
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

export default DeliveryForm
