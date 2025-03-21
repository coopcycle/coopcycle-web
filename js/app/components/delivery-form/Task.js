import React, { useEffect, useState } from 'react'
import { useFormikContext, Field } from 'formik'
import AddressBookNew from './AddressBook'
import SwitchTimeSlotFreePicker from './SwitchTimeSlotFreePicker'
import { Input, Button } from 'antd'
import DateRangePicker from './DateRangePicker'
import Packages from './Packages'
import { useTranslation } from 'react-i18next'
import TotalWeight from './TotalWeight'
import Spinner from '../core/Spinner'
import TimeSlotPicker from './TimeSlotPicker'

import './Task.scss'
import TagsSelect from '../TagsSelect'

const renderTimeSlotPicker = ({isDispatcher, storeDeliveryInfos, index,  format, isTimeSlotSelect, setIsTimeSlotSelect, timeSlotLabels}) => 
  isDispatcher ? 
    (<SwitchTimeSlotFreePicker
      storeDeliveryInfos={storeDeliveryInfos}
      index={index}
      format={format}
      isTimeSlotSelect={isTimeSlotSelect}
      setIsTimeSlotSelect={setIsTimeSlotSelect}
      isDispatcher={isDispatcher}
      timeSlotLabels={timeSlotLabels}
    />)
  :  (<TimeSlotPicker
        storeDeliveryInfos={storeDeliveryInfos}
        index={index}
        timeSlotLabels={timeSlotLabels}
      />)


const renderDatePart = ({isDispatcher, isEdit, storeDeliveryInfos, index,  format, isTimeSlotSelect, setIsTimeSlotSelect, timeSlotLabels}) => {
    if (!Array.isArray(storeDeliveryInfos.timeSlots)) { // not loaded yet
      return <Spinner />
    } else if (storeDeliveryInfos.timeSlots.length > 0 && !isEdit) {
      return renderTimeSlotPicker({isDispatcher, storeDeliveryInfos, index,  format, isTimeSlotSelect, setIsTimeSlotSelect, timeSlotLabels})
    } else {
      return <DateRangePicker format={format} index={index} isDispatcher={isDispatcher} />
    }
}

export default ({
  addresses,
  storeId,
  index,
  storeDeliveryInfos,
  isEdit,
  onRemove,
  showRemoveButton,
  packages,
  isDispatcher,
  tags,
  timeSlotLabels
}) => {
  const { t } = useTranslation()

  const { values, setFieldValue } = useFormikContext()
  const task = values.tasks[index]

  const format = 'LL'

  const [showLess, setShowLess] = useState(false)
  const [isTimeSlotSelect, setIsTimeSlotSelect] = useState(true)

  useEffect(() => {
    if (
      isTimeSlotSelect &&
      storeDeliveryInfos.timeSlots?.length > 0 &&
      !isEdit
    ) {
      setFieldValue(`tasks[${index}].after`, null)
      setFieldValue(`tasks[${index}].before`, null)
    } else {
      setFieldValue(`tasks[${index}].timeSlot`, null)
    }
  }, [isTimeSlotSelect, storeDeliveryInfos])

  useEffect(() => {
    const shouldShowLess =
      task.type === 'DROPOFF' &&
      values.tasks.length > 2 &&
      index !== values.tasks.length - 1
    setShowLess(shouldShowLess)
  }, [task.type, values.tasks.length, index])

  return (
    <div className="task border p-4 mb-4" data-testid-form={`task-${index}`}>
      <div
        className={
          task.type === 'PICKUP'
            ? 'task__header task__header--pickup'
            : 'task__header task__header--dropoff'
        }
        onClick={() => setShowLess(!showLess)}>
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
            }></i>
        </button>
      </div>

      <div
        className={!showLess ? 'task__body' : 'task__body task__body--hidden'}>
        <AddressBookNew
          addresses={addresses}
          index={index}
          storeDeliveryInfos={storeDeliveryInfos}
          shallPrefillAddress={Boolean(task.type === 'PICKUP' && !isEdit && storeDeliveryInfos.prefillPickupAddress)}
        />

        { renderDatePart({isDispatcher, isEdit, storeDeliveryInfos, index,  format, isTimeSlotSelect, setIsTimeSlotSelect, timeSlotLabels}) }

        {task.type === 'DROPOFF' ? (
          <div className="mt-4">
            {packages ? (
              <Packages
                storeId={storeId}
                index={index}
                packages={packages}
                isEdit={isEdit}
              />
            ) : null}
            <TotalWeight index={index} />
          </div>
        ) : null}

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

        {isDispatcher && (
          <div className="mt-4 mb-4">
            <div className="tags__title block mb-2 font-weight-bold">Tags</div>
            <TagsSelect
              tags={tags}
              defaultValue={values.tasks[index].tags || []}
              onChange={values => {
                const tags = values.map(tag => tag.value)
                setFieldValue(`tasks[${index}].tags`, tags)
              }}
            />
          </div>
        )}
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
        </div>
      )}
    </div>
  )
}
