import React from 'react'
import { useTranslation } from 'react-i18next'
import DeprecatedTag from '../../../components/DeprecatedTag'
import { RuleTarget } from '../types/PricingRuleType'

type Props = {
  className?: string
  target: RuleTarget
}

export default function PricingRuleTarget({ className = '', target }: Props) {
  const { t } = useTranslation()

  const labels = {
    DELIVERY: t('RULE_TARGET_DELIVERY'),
    TASK: t('RULE_TARGET_TASK'),
    LEGACY_TARGET_DYNAMIC: t('RULE_LEGACY_TARGET_DYNAMIC'),
  }

  return (
    <div className={className}>
      {target === 'LEGACY_TARGET_DYNAMIC' && (
        <DeprecatedTag tooltipText={t('RULE_LEGACY_TARGET_DYNAMIC_HELP')} />
      )}
      {labels[target] ?? '?'}{' '}
    </div>
  )
}
