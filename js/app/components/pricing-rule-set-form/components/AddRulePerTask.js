import React from 'react'
import { useTranslation } from 'react-i18next'
import { PlusOutlined } from '@ant-design/icons'
import HelpIcon from '../../../components/HelpIcon'
import { Button } from '../../core/AntdButton'

export default function AddRulePerTask({ onAddRule }) {
  const { t } = useTranslation()

  return (
    <div>
      <Button
        success
        icon={<PlusOutlined />}
        onClick={() => onAddRule('TASK')}
        data-testid="pricing-rule-set-add-rule-target-task">
        {t('PRICING_ADD_RULE_PER_TASK')}
      </Button>
      <HelpIcon
        className="ml-1"
        tooltipText={t('PRICING_ADD_RULE_PER_TASK_HELP')}
      />
    </div>
  )
}
