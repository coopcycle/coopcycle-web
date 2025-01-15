import React, { useCallback, useEffect, useState } from 'react'
import { useFormikContext, Field } from 'formik'
import AddressBookNew from './AddressBookNew'
import SwitchTimeSlotFreePicker from './SwitchTimeSlotFreePicker'
import { Input, Button } from 'antd'
import DateRangePicker from './DateRangePicker'
import Packages from './Packages'
import { useTranslation } from 'react-i18next'
import TotalWeight from './TotalWeight'

import './Task.scss'

const baseURL = location.protocol + '//' + location.host

export default ({
  addresses,
  storeId,
  index,
  storeDeliveryInfos,
  deliveryId,
  onAdd,
  dropoffSchema,
  onRemove,
  showRemoveButton,
  showAddButton,
}) => {
  const httpClient = new window._auth.httpClient()

  const { t } = useTranslation()

  const { values } = useFormikContext()
  const task = values.tasks[index]

  const format = 'LL'

  const [packages, setPackages] = useState(null)
  const [showLess, setShowLess] = useState(false)

  useEffect(() => {
    const shouldShowLess =
      task.type === 'DROPOFF' &&
      values.tasks.length > 2 &&
      index !== values.tasks.length - 1
    setShowLess(shouldShowLess)
  }, [task.type, values.tasks.length, index])


  useEffect(() => {
    const getPackages = async () => {
      const url = `${baseURL}/api/stores/${storeId}/packages`

      const { response } = await httpClient.get(url)

      if (response) {
        const packages = response['hydra:member']
        if (packages?.length > 0) {
          setPackages(packages)
        }
      }
    }
    getPackages()
  }, [storeId])

  const areDefinedTimeSlots = useCallback(() => {
    return (
      storeDeliveryInfos &&
      Array.isArray(storeDeliveryInfos.timeSlots) &&
      storeDeliveryInfos.timeSlots.length > 0
    )
  }, [storeDeliveryInfos])

  return (
    <div className="task border p-4 mb-4">
      <div
        className={
          task.type === 'PICKUP'
            ? 'task__header task__header--pickup'
            : 'task__header task__header--dropoff'
        }>
        {task.type === 'PICKUP' ? (
          <i className="fa fa-arrow-up"></i>
        ) : (
          <i className="fa fa-arrow-down"></i>
        )}
        <h3 className="task__header__title mb-4">
          {task.type === 'PICKUP'
            ? t('DELIVERY_FORM_PICKUP_INFORMATIONS')
            : t('DELIVERY_FORM_DROPOFF_INFORMATIONS')}
        </h3>

        <button className="task__button">
          <i
            className={!showLess ? 'fa fa-minus-circle' : 'fa fa-plus-circle'}
            title={
              showLess ? 'Show less informations' : 'Show more informations'
            }
            onClick={() => setShowLess(!showLess)}></i>
        </button>
      </div>

      <div
        className={!showLess ? 'task__body' : 'task__body task__body--hidden'}>
        <AddressBookNew addresses={addresses} index={index} />

        {task.type === 'DROPOFF' ? (
          <div>
            {packages ? (
              <Packages
                storeId={storeId}
                index={index}
                packages={packages}
                deliveryId={deliveryId}
              />
            ) : null}
            <TotalWeight index={index} deliveryId={deliveryId} />
          </div>
        ) : null}

        {areDefinedTimeSlots() & !deliveryId ? (
          <SwitchTimeSlotFreePicker
            storeId={storeId}
            storeDeliveryInfos={storeDeliveryInfos}
            index={index}
            format={format}
            deliveryId={deliveryId}
          />
        ) : (
          <DateRangePicker format={format} index={index} />
        )}
        <div className="mt-4 mb-4">
          <label
            htmlFor={`tasks[${index}].comments`}
            className="block mb-2 font-weight-bold">
            {t('ADMIN_DASHBOARD_TASK_FORM_COMMENTS_LABEL')}
          </label>
          <Field
            as={Input.TextArea}
            name={`tasks[${index}].comments`}
            placeholder={t('ADMIN_DASHBOARD_TASK_FORM_COMMENTS_PLACEHOLDER')}
            rows={4}
            style={{ resize: 'none' }}
          />
        </div>
      </div>
      {task.type === 'DROPOFF' && (
        <div className={!showLess ? 'task__footer' : 'task__footer--hidden'}>
          {showRemoveButton && (
            <Button
              onClick={() => onRemove(index)}
              type="button"
              className="mb-4">
              {t('DELIVERY_FORM_REMOVE_DROPOFF')}
            </Button>
          )}
          {showAddButton && (
            <div
              className="mb-4"
              style={{
                display: 'flex',
                justifyContent: 'space-between',
                alignItems: 'center',
              }}>
              <p>{t('DELIVERY_FORM_MULTIDROPOFF')}</p>
              <Button
                disabled={false}
                onClick={() => {
                  onAdd(dropoffSchema)
                }}>
                {t('DELIVERY_FORM_ADD_DROPOFF')}
              </Button>
            </div>
          )}
        </div>
      )}
    </div>
  )
}
