import React from 'react'
import { InputNumber } from 'antd'

type Props = {
  defaultValue: { value: string }
  onChange: (value: any) => void
}

export default ({ defaultValue, onChange } : Props) => {
  const [value, setValue] = React.useState(
    parseFloat(defaultValue.value) / 100 || 0,
  )

  //TODO: fix currency?
  return (
    <InputNumber
      data-testid="rule-fixed-price-input"
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
