import React from 'react'
import { useTranslation } from 'react-i18next'
import TimeSlotPicker from './TimeSlotPicker'
import DateRangePicker from './DateRangePicker'
import { useFormikContext } from 'formik'

import './SwitchTimeSlotFreePicker.scss'

export default ({
  storeId,
  storeDeliveryInfos,
  index,
  format,
  isTimeSlotSelect,
  setIsTimeSlotSelect,
}) => {
  const { t } = useTranslation()

  const { errors } = useFormikContext()

  return (
    <>
      {isTimeSlotSelect ? (
        <div className="timeslot-container">
          <div className="timeslot-container__picker" style={{ width: '95%' }}>
            <TimeSlotPicker
              storeId={storeId}
              storeDeliveryInfos={storeDeliveryInfos}
              index={index}
            />
          </div>
          <i
            className="timeslot-container__icon fa fa-calendar"
            aria-hidden="true"
            onClick={() => setIsTimeSlotSelect(!isTimeSlotSelect)}
            title={t('SWITCH_TIMESLOTPICKER')}></i>
        </div>
      ) : (
        <div className="daterange-picker-container">
          <div className="daterange-picker-container__picker">
            <DateRangePicker format={format} index={index} />
          </div>
          <i
            className="daterange-picker-container__icon fa fa-calendar text-right"
            aria-hidden="true"
            onClick={() => setIsTimeSlotSelect(!isTimeSlotSelect)}
            title={t('SWITCH_DATEPICKER')}></i>
        </div>
      )}
      {errors.tasks?.[index]?.before && (
        <div className="text-danger">{errors.tasks[index].before}</div>
      )}
    </>
  )
}
