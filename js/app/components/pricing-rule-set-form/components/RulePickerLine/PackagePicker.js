import React from 'react'
import { Select } from 'antd'
import { useGetPackagesQuery } from '../../../../api/slice'
import PickerIsLoading from './PickerIsLoading'
import PickerIsError from './PickerIsError'

export default function PackagePicker({ value, onChange }) {
  const { data: packages, isFetching } = useGetPackagesQuery()

  if (isFetching) {
    return <PickerIsLoading />
  }

  if (!packages) {
    return <PickerIsError />
  }

  return (
    <Select
      onChange={value =>
        // replicate on change signature of html input until we re-write PricePickerLine component
        onChange({
          target: {
            value: value,
          },
        })
      }
      value={value}
      options={[
        { value: '', label: '-' },
        ...packages.map(item => ({
          value: item.name,
          label: item.name,
        })),
      ]}
    />
  )
}
