import { InputNumber } from 'antd'
import React from 'react'

export default ({ defaultValue, onChange }) => {
  const [value, setValue] = React.useState(
    parseFloat(defaultValue.value) / 100 || 0,
  )

  //TODO: fix currency?
  return (
    <InputNumber
      value={value}
      onChange={value => {
        setValue(value)
        onChange(`${(value || 0) * 100}`)
      }}
      style={{ width: '100%' }}
      step={0.5}
      min={0}
      precision={2}
      addonAfter="€"
    />
  )
}
