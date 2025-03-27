import React from 'react'
import { useTranslation } from 'react-i18next'
import HelpIcon from '../../../components/HelpIcon'
import Button from '../../../components/core/Button'

export default function AddRulePerTask({ onAddRule }) {
  const { t } = useTranslation()

  return (
    <div>
      <Button
        success
        icon="plus"
        onClick={() => onAddRule('TASK')}
        testID="pricint_rule_set_add_rule_target_task">
        {t('PRICING_ADD_RULE_PER_TASK')}
      </Button>
      <HelpIcon
        className="ml-1"
        tooltipText={t('PRICING_ADD_RULE_PER_TASK_HELP')}
      />
    </div>
  )
}
