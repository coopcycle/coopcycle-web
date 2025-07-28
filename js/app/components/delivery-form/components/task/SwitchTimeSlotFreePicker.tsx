import React from 'react'
import { useTranslation } from 'react-i18next'
import TimeSlotPicker from './TimeSlotPicker'
import DateRangePicker from './DateRangePicker'
import { TimeSlot } from '../../types'

import './SwitchTimeSlotFreePicker.scss'

type Props = {
  isDispatcher: boolean
  storeNodeId: string
  taskId: string
  format: string
  isTimeSlotSelect: boolean
  setIsTimeSlotSelect: (value: boolean) => void
  timeSlotLabels: TimeSlot[]
}

const SwitchTimeSlotFreePicker = ({
  isDispatcher,
  storeNodeId,
  taskId,
  format,
  isTimeSlotSelect,
  setIsTimeSlotSelect,
  timeSlotLabels
} : Props) => {
  const { t } = useTranslation()

  return (
    <>
      {isTimeSlotSelect ? (
        <div className="timeslot-container">
          <div className="timeslot-container__picker" style={{ width: '95%' }}>
            <TimeSlotPicker
              storeNodeId={storeNodeId}
              taskId={taskId}
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
            <DateRangePicker format={format} taskId={taskId} isDispatcher={isDispatcher} />
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

export default SwitchTimeSlotFreePicker
