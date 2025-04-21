import { useTranslation } from 'react-i18next'
import React from 'react'

export function PriceChoice({ defaultValue, onChange }) {
  const { t } = useTranslation()

  return (
    <select
      data-testid="pricing_rule_price_type_choice"
      onChange={e => onChange(e.target.value)}
      defaultValue={defaultValue}>
      <option value="fixed">{t('PRICE_RANGE_EDITOR.TYPE_FIXED')}</option>
      <option value="percentage">
        {t('PRICE_RANGE_EDITOR.TYPE_PERCENTAGE')}
      </option>
      <option value="range">{t('PRICE_RANGE_EDITOR.TYPE_RANGE')}</option>
      <option value="per_package">
        {t('PRICE_RANGE_EDITOR.TYPE_PER_PACKAGE')}
      </option>
    </select>
  )
}
