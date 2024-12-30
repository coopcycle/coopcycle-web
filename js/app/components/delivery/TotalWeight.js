import React, { useEffect, useState } from 'react'
import { InputNumber, Select } from 'antd'
const { Option } = Select
import { useFormikContext } from 'formik'

export default ({ index }) => {
  const { setFieldValue } = useFormikContext()

  const [numberValue, setNumberValue] = useState(null)
  const [weightUnit, setWeightUnit] = useState('Kg')

  useEffect(() => {
    let weight
    if (numberValue) {
      if (weightUnit === 'kg') {
        weight = numberValue * 1000
        setFieldValue(`tasks[${index}].weight`, weight)
      }
      if (weightUnit === 'lbs') {
        weight = numberValue * 453.592
        setFieldValue(`tasks[${index}].weight`, weight)
      }
    }
  }, [numberValue, weightUnit])

  return (
    <>
      <div>Total Weight</div>
      <InputNumber
        placeholder="Weight"
        value={numberValue}
        onChange={value => {
          setNumberValue(value)
        }}
      />
      <Select value={weightUnit} onChange={value => setWeightUnit(value)}>
        <Option value="kg">Kg</Option>
        <Option value="lbs">Lbs</Option>
      </Select>
    </>
  )
}
