import React, { useEffect, useState } from 'react'
import Spinner from '../core/Spinner'
import SwitchTimeSlotFreePicker from './SwitchTimeSlotFreePicker'
import TimeSlotPicker from './TimeSlotPicker'
import DateRangePicker from './DateRangePicker'
import { useDeliveryFormFormikContext } from './hooks/useDeliveryFormFormikContext'
import { useGetStoreQuery } from '../../api/slice'

export const TaskDateTime = ({ isDispatcher, storeId, timeSlots, index }) => {
  const format = 'LL'

  const { data: store } = useGetStoreQuery(storeId)

  const { isModifyOrderMode, setFieldValue } = useDeliveryFormFormikContext({
    taskIndex: index,
  })

  const timeSlotIds = store?.timeSlots
  const [isTimeSlotSelect, setIsTimeSlotSelect] = useState(true)

  useEffect(() => {
    if (isTimeSlotSelect && timeSlotIds?.length > 0) {
      if (isModifyOrderMode) {
        setFieldValue(`tasks[${index}].timeSlot`, null)
      } else {
        setFieldValue(`tasks[${index}].after`, null)
        setFieldValue(`tasks[${index}].before`, null)
      }
    } else {
      setFieldValue(`tasks[${index}].timeSlot`, null)
      setFieldValue(`tasks[${index}].timeSlotUrl`, null)
    }
  }, [isTimeSlotSelect, timeSlotIds, index, setFieldValue, isModifyOrderMode])

  if (!Array.isArray(timeSlotIds)) {
    // not loaded yet
    return <Spinner />
  } else if (timeSlotIds.length > 0 && !isModifyOrderMode) {
    if (isDispatcher) {
      return (
        <SwitchTimeSlotFreePicker
          isDispatcher={isDispatcher}
          storeId={storeId}
          index={index}
          format={format}
          isTimeSlotSelect={isTimeSlotSelect}
          setIsTimeSlotSelect={setIsTimeSlotSelect}
          timeSlotLabels={timeSlots}
        />
      )
    } else {
      return (
        <TimeSlotPicker
          storeId={storeId}
          index={index}
          timeSlotLabels={timeSlots}
        />
      )
    }
  } else {
    return (
      <DateRangePicker
        format={format}
        index={index}
        isDispatcher={isDispatcher}
      />
    )
  }
}
