import React, { useEffect, useState } from 'react'
import Spinner from '../core/Spinner'
import SwitchTimeSlotFreePicker from './SwitchTimeSlotFreePicker'
import TimeSlotPicker from './TimeSlotPicker'
import DateRangePicker from './DateRangePicker'
import { useDeliveryFormFormikContext } from './hooks/useDeliveryFormFormikContext'
import { useGetStoreQuery } from '../../api/slice'
import { Mode } from './mode'
import { useSelector } from 'react-redux'
import { selectMode } from './redux/formSlice'

export const TaskDateTime = ({ isDispatcher, storeNodeId, timeSlots, index }) => {
  const format = 'LL'

  const { data: store } = useGetStoreQuery(storeNodeId)

  const mode = useSelector(selectMode)
  const { setFieldValue } = useDeliveryFormFormikContext({
    taskIndex: index,
  })

  const timeSlotIds = store?.timeSlots
  const [isTimeSlotSelect, setIsTimeSlotSelect] = useState(true)

  useEffect(() => {
    if (mode === Mode.DELIVERY_UPDATE) {
      setFieldValue(`tasks[${index}].timeSlot`, null)
    } else {
      if (isTimeSlotSelect && timeSlotIds?.length > 0) {
        setFieldValue(`tasks[${index}].after`, null)
        setFieldValue(`tasks[${index}].before`, null)
      } else {
        setFieldValue(`tasks[${index}].timeSlot`, null)
        setFieldValue(`tasks[${index}].timeSlotUrl`, null)
      }
    }
  }, [isTimeSlotSelect, timeSlotIds, index, setFieldValue, mode])

  if (!Array.isArray(timeSlotIds)) {
    // not loaded yet
    return <Spinner />
  } else if (timeSlotIds.length > 0 && mode === Mode.DELIVERY_CREATE) {
    if (isDispatcher) {
      return (
        <SwitchTimeSlotFreePicker
          isDispatcher={isDispatcher}
          storeNodeId={storeNodeId}
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
          storeNodeId={storeNodeId}
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
