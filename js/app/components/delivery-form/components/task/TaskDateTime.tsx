import React, { useEffect, useState } from 'react'
import Spinner from '../../../core/Spinner'
import SwitchTimeSlotFreePicker from './SwitchTimeSlotFreePicker'
import TimeSlotPicker from './TimeSlotPicker'
import DateRangePicker from './DateRangePicker'
import { useDeliveryFormFormikContext } from '../../hooks/useDeliveryFormFormikContext'
import { useGetStoreQuery } from '../../../../api/slice'
import { Mode } from '../../mode'
import { useSelector } from 'react-redux'
import { selectMode } from '../../redux/formSlice'
import { StoreTimeSlot } from '../../../../api/types'

type Props = {
  isDispatcher: boolean
  storeNodeId: string
  timeSlots: StoreTimeSlot[]
  taskId: string
}

export const TaskDateTime = ({
  isDispatcher,
  storeNodeId,
  timeSlots,
  taskId,
}: Props) => {
  const format = 'LL'

  const { data: store } = useGetStoreQuery(storeNodeId)

  const mode = useSelector(selectMode)
  const { setFieldValue, taskIndex } = useDeliveryFormFormikContext({
    taskId: taskId,
  })

  const timeSlotIds = store?.timeSlots
  const [isTimeSlotSelect, setIsTimeSlotSelect] = useState<boolean>(true)

  useEffect(() => {
    if (mode === Mode.DELIVERY_UPDATE) {
      setFieldValue(`tasks[${taskIndex}].timeSlot`, null)
    } else if (mode === Mode.DELIVERY_CREATE) {
      if (isTimeSlotSelect && timeSlotIds?.length > 0) {
        setFieldValue(`tasks[${taskIndex}].after`, null)
        setFieldValue(`tasks[${taskIndex}].before`, null)
      } else {
        setFieldValue(`tasks[${taskIndex}].timeSlot`, null)
        setFieldValue(`tasks[${taskIndex}].timeSlotUrl`, null)
      }
    }
  }, [isTimeSlotSelect, timeSlotIds, taskIndex, setFieldValue, mode])

  if (!Array.isArray(timeSlotIds)) {
    // not loaded yet
    return <Spinner />
  } else if (timeSlotIds.length > 0 && mode === Mode.DELIVERY_CREATE) {
    if (isDispatcher) {
      return (
        <SwitchTimeSlotFreePicker
          isDispatcher={isDispatcher}
          storeNodeId={storeNodeId}
          taskId={taskId}
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
          taskId={taskId}
          timeSlotLabels={timeSlots}
        />
      )
    }
  } else {
    return (
      <DateRangePicker
        format={format}
        taskId={taskId}
        isDispatcher={isDispatcher}
      />
    )
  }
}
