import React from 'react'
import { useTranslation } from 'react-i18next'

export default function PricingRuleSetActions({ onAddRule }) {
  const { t } = useTranslation()

  return (
    <div className="d-flex justify-content-end gap-2">
      <button
        type="button"
        className="btn btn-success"
        onClick={() => onAddRule('DELIVERY')}>
        <i className="fa fa-plus"></i>
        {t('PRICING_ADD_RULE')}
      </button>
      <button
        type="button"
        className="btn btn-success"
        onClick={() => onAddRule('TASK')}>
        <i className="fa fa-plus"></i>
        {t('PRICING_ADD_RULE_PER_TASK')}
      </button>
    </div>
  )
}
