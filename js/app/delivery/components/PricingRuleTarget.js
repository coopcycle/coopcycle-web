import React from 'react'
import { useTranslation } from 'react-i18next'

export default function PricingRuleTarget({ target }) {
  const { t } = useTranslation()

  const labels = {
    DELIVERY: t('RULE_TARGET_DELIVERY'),
    TASK: t('RULE_TARGET_TASK'),
  }

  return <div className="mx-4 my-2">{labels[target] ?? '?'}</div>
}
