import React, { useEffect, useState } from 'react'
import { useFormikContext, Field } from 'formik'
import AddressBookNew from './AddressBookNew'
import SwitchTimeSlotFreePicker from './SwitchTimeSlotFreePicker'
import { Input, Button } from 'antd'
import DateRangePicker from './DateRangePicker'
import Packages from './Packages'
import { useTranslation } from 'react-i18next'
import TotalWeight from './TotalWeight'
import TimeSlotPicker from './TimeSlotPicker'

import './Task.scss'

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
  isAdmin,
  areDefinedTimeSlots,
  packages,
}) => {
  const { t } = useTranslation()

  const { values, setFieldValue } = useFormikContext()
  const task = values.tasks[index]

  const format = 'LL'

  const [showLess, setShowLess] = useState(false)

  useEffect(() => {
    const shouldShowLess =
      task.type === 'DROPOFF' &&
      values.tasks.length > 2 &&
      index !== values.tasks.length - 1
    setShowLess(shouldShowLess)
  }, [task.type, values.tasks.length, index])

  // we have to set after and before to null here - if not admin - unless we have timeslot values and after/before for stores
  useEffect(() => {
    if (areDefinedTimeSlots) {
      setFieldValue(`tasks[${index}].after`, null)
      setFieldValue(`tasks[${index}].before`, null)
    }
  }, [isAdmin, areDefinedTimeSlots])

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

        <button type="button" className="task__button">
          <i
            className={!showLess ? 'fa fa-chevron-up' : 'fa fa-chevron-down'}
            title={
              showLess
                ? t('DELIVERY_FORM_SHOW_MORE')
                : t('DELIVERY_FORM_SHOW_LESS')
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
        {isAdmin ? (
          areDefinedTimeSlots && !deliveryId ? (
            <SwitchTimeSlotFreePicker
              storeId={storeId}
              storeDeliveryInfos={storeDeliveryInfos}
              index={index}
              format={format}
              deliveryId={deliveryId}
              isAdmin={isAdmin}
            />
          ) : (
            <DateRangePicker format={format} index={index} isAdmin={isAdmin} />
          )
        ) : areDefinedTimeSlots ? (
          <TimeSlotPicker
            storeId={storeId}
            storeDeliveryInfos={storeDeliveryInfos}
            index={index}
          />
        ) : (
          <DateRangePicker format={format} index={index} isAdmin={isAdmin} />
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
