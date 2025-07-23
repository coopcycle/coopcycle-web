import React from 'react'
import { useTranslation } from 'react-i18next'
import { Select } from 'antd'
import { PriceType } from '../types/PricingRuleType'

const { Option } = Select

type Props = {
  priceType: PriceType
  handlePriceTypeChange: (type: PriceType) => void
}

export function PriceChoice({ priceType, handlePriceTypeChange }: Props) {
  const { t } = useTranslation()

  return (
    <Select
      data-testid="rule-price-type"
      style={{ minWidth: 240 }}
      value={priceType}
      onChange={handlePriceTypeChange}>
      <Option value="fixed">{t('PRICE_RANGE_EDITOR.TYPE_FIXED')}</Option>
      <Option value="percentage">
        {t('PRICE_RANGE_EDITOR.TYPE_PERCENTAGE')}
      </Option>
      <Option value="range">{t('PRICE_RANGE_EDITOR.TYPE_RANGE')}</Option>
      <Option value="per_package">
        {t('PRICE_RANGE_EDITOR.TYPE_PER_PACKAGE')}
      </Option>
    </Select>
  )
}
