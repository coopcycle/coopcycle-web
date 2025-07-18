import React from 'react'
import { InputNumber } from 'antd'
import PercentageEditor from './PercentageEditor'
import PriceRangeEditor from './PriceRangeEditor'
import PricePerPackageEditor from './PricePerPackageEditor'
import PriceFixedEditor from './PriceFixedEditor'

type Props = {
  priceType: string
  defaultValue: any
  onChange: (value: any) => void
}

export default function PriceEditor({ priceType, defaultValue, onChange } : Props) {
  switch (priceType) {
    case 'percentage':
      return (
        <PercentageEditor
          defaultValue={defaultValue}
          onChange={({ percentage }) => {
            onChange(`price_percentage(${percentage})`)
          }}
        />
      )
    case 'range':
      return (
        <PriceRangeEditor
          defaultValue={defaultValue}
          onChange={({ attribute, price, step, threshold }) => {
            onChange(
              `price_range(${attribute}, ${price}, ${step}, ${threshold})`,
            )
          }}
        />
      )
    case 'per_package':
      return (
        <PricePerPackageEditor
          defaultValue={defaultValue}
          onChange={({ packageName, unitPrice, offset, discountPrice }) => {
            onChange(
              `price_per_package(packages, "${packageName}", ${unitPrice}, ${offset}, ${discountPrice})`,
            )
          }}
        />
      )
    case 'fixed':
    default:
      return (
        <PriceFixedEditor
          defaultValue={defaultValue}
          onChange={value => onChange(value)}
        />
      )
  }
}
