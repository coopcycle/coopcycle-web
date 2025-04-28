import React from 'react'
import { useGetTimeSlotsQuery } from '../../../api/slice'
import PickerIsLoading from './PickerIsLoading'
import PickerIsError from './PickerIsError'

export default function TimeSlotPicker({ value, onChange }) {
  const { data, isFetching } = useGetTimeSlotsQuery()

  if (isFetching) {
    return <PickerIsLoading />
  }

  if (!data) {
    return <PickerIsError />
  }

  const timeSlots = data['hydra:member']

  if (!timeSlots) {
    return <PickerIsError />
  }

  return (
    <select onChange={onChange} value={value} className="form-control input-sm">
      <option value="">-</option>
      {timeSlots.map((timeSlot, index) => {
        return (
          <option value={timeSlot['@id']} key={index}>
            {timeSlot.name}
          </option>
        )
      })}
    </select>
  )
}
