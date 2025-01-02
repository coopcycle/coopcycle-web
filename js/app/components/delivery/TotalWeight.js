import React, { useEffect, useState } from 'react'
import { InputNumber, Select } from 'antd'
const { Option } = Select
import { useFormikContext } from 'formik'

export default ({ index }) => {
  const { setFieldValue, setFieldTouched, errors, touched } = useFormikContext()

  const [numberValue, setNumberValue] = useState(null)
  const [weightUnit, setWeightUnit] = useState('kg')

  useEffect(() => {
    if (numberValue !== null) {
      let calculatedWeight = 0
      if (weightUnit === 'kg') {
        calculatedWeight = numberValue * 1000
      } else if (weightUnit === 'lbs') {
        calculatedWeight = numberValue * 453.592
      }
      setFieldValue(`tasks[${index}].weight`, calculatedWeight)
    } else {
      setFieldValue(`tasks[${index}].weight`, 0)
    }
  }, [numberValue, weightUnit, index])

  const handleBlur = () => {
    setFieldTouched(`tasks[${index}].weight`, true, true)
  }

  return (
    <>
      <div>Total Weight</div>
      <InputNumber
        min={0}
        placeholder="Weight"
        value={numberValue}
        onChange={value => {
          setNumberValue(value)
        }}
        onBlur={handleBlur}
      />
      <Select value={weightUnit} onChange={value => setWeightUnit(value)}>
        <Option value="kg">Kg</Option>
        <Option value="lbs">Lbs</Option>
      </Select>

      {errors.tasks?.[index]?.weight && touched.tasks?.[index]?.weight && (
        <div className="text-danger">{errors.tasks[index].weight}</div>
      )}
    </>
  )
}
