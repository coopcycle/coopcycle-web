import React from 'react'
import { useTranslation } from 'react-i18next'
import HelpIcon from '../../../components/HelpIcon'
import Button from '../../../components/core/Button'

export default function AddRulePerDelivery({ onAddRule }) {
  const { t } = useTranslation()

  return (
    <div>
      <Button
        success
        icon="plus"
        onClick={() => onAddRule('DELIVERY')}
        testID="pricing_rule_set_add_rule_target_delivery">
        {t('PRICING_ADD_RULE')}
      </Button>
      <HelpIcon className="ml-1" tooltipText={t('PRICING_ADD_RULE_HELP')} />
    </div>
  )
}
