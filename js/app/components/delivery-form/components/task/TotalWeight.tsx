import React, { useEffect, useState } from 'react'
import { InputNumber, Select } from 'antd'
const { Option } = Select
import { useTranslation } from 'react-i18next'
import {
  useDeliveryFormFormikContext
} from '../../hooks/useDeliveryFormFormikContext'

type Props = {
  taskId: string
}

const TotalWeight = ({ taskId }: Props) => {
  const { setFieldValue, errors, values, taskIndex: index } = useDeliveryFormFormikContext({
    taskId: taskId,
  })

  const [numberValue, setNumberValue] = useState<number>(
    values.tasks[index].weight / 1000,
  )
  const [weightUnit, setWeightUnit] = useState<'kg' | 'lbs'>('kg')

  const { t } = useTranslation()

  useEffect(() => {
    if (numberValue !== null) {
      let calculatedWeight = 0
      if (weightUnit === 'kg') {
        calculatedWeight = numberValue * 1000
      } else if (weightUnit === 'lbs') {
        calculatedWeight = Math.round(numberValue * 453.592)
      }
      setFieldValue(`tasks[${index}].weight`, calculatedWeight)
    }
  }, [numberValue, weightUnit, index])

  return (
    <div className="mt-4 mb-4">
      <div className="mb-2 font-weight-bold">
        {t('DELIVERY_FORM_TOTAL_WEIGHT')}
      </div>
      <div style={{ display: 'flex', justifyContent: 'space-between' }}>
        <InputNumber
          name={`tasks[${index}].weight`}
          style={{ width: '80%' }}
          min={0}
          placeholder={t('DELIVERY_FORM_WEIGHT')}
          value={numberValue}
          onChange={(value: number | null) => {
            setNumberValue(value || 0)
          }}
        />
        <Select
          style={{ width: '15%' }}
          value={weightUnit}
          onChange={(value: 'kg' | 'lbs') => setWeightUnit(value)}>
          <Option value="kg">Kg</Option>
          <Option value="lbs">Lbs</Option>
        </Select>
      </div>

      {errors.tasks?.[index]?.weight && (
        <div className="text-danger">{errors.tasks[index].weight}</div>
      )}
    </div>
  )
}

export default TotalWeight
