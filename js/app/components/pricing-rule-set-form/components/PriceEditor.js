import React from 'react'
import { InputNumber } from 'antd'
import PercentageEditor from './PercentageEditor'
import PriceRangeEditor from './PriceRangeEditor'
import PricePerPackageEditor from './PricePerPackageEditor'

export default function PriceEditor({ priceType, defaultValue, onChange }) {
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
      //TODO: fix currency?
      return (
        <InputNumber
          value={parseFloat(defaultValue.value) / 100 || 0}
          onChange={value => onChange((value || 0) * 100)}
          style={{ width: '100%' }}
          step={0.01}
          min={0}
          precision={2}
          addonAfter="€"
        />
      )
  }
}
