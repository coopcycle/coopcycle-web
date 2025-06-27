import React from 'react'
import { Select } from 'antd'
import { useGetTimeSlotsQuery } from '../../../../api/slice'
import PickerIsLoading from './PickerIsLoading'
import PickerIsError from './PickerIsError'

export default function TimeSlotPicker({ value, onChange }) {
  const { data: timeSlots, isFetching } = useGetTimeSlotsQuery()

  if (isFetching) {
    return <PickerIsLoading />
  }

  if (!timeSlots) {
    return <PickerIsError />
  }

  return (
    <Select
      onChange={onChange}
      value={value}
      options={[
        { value: '', label: '-' },
        ...timeSlots.map(item => ({
          value: item['@id'],
          label: item.name,
        })),
      ]}
    />
  )
}
