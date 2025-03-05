import React from 'react'
import { useTranslation } from 'react-i18next'

export default function PricingRuleSetActions({ onClick }) {
  const { t } = useTranslation()

  return (
    <button type="button" className="btn btn-success" onClick={onClick}>
      <i className="fa fa-plus"></i>
      {t('PRICING_ADD_RULE')}
    </button>
  )
}
