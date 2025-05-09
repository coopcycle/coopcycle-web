import React from 'react'
import { useTranslation } from 'react-i18next'
import TimeSlotPicker from './TimeSlotPicker'
import DateRangePicker from './DateRangePicker'

import './SwitchTimeSlotFreePicker.scss'

export default ({
  isDispatcher,
  storeNodeId,
  index,
  format,
  isTimeSlotSelect,
  setIsTimeSlotSelect,
  timeSlotLabels
}) => {
  const { t } = useTranslation()

  return (
    <>
      {isTimeSlotSelect ? (
        <div className="timeslot-container">
          <div className="timeslot-container__picker" style={{ width: '95%' }}>
            <TimeSlotPicker
              storeNodeId={storeNodeId}
              index={index}
              timeSlotLabels={timeSlotLabels}
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
            <DateRangePicker format={format} index={index} isDispatcher={isDispatcher} />
          </div>
          <i
            className="daterange-picker-container__icon fa fa-calendar text-right"
            aria-hidden="true"
            onClick={() => setIsTimeSlotSelect(!isTimeSlotSelect)}
            title={t('SWITCH_DATEPICKER')}></i>
        </div>
      )}
    </>
  )
}
