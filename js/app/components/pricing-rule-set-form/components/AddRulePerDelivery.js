import React from 'react'
import { useTranslation } from 'react-i18next'
import { PlusOutlined } from '@ant-design/icons'
import HelpIcon from '../../../components/HelpIcon'
import { Button } from '../../core/AntdButton'

export default function AddRulePerDelivery({ onAddRule }) {
  const { t } = useTranslation()

  return (
    <div>
      <Button
        success
        icon={<PlusOutlined />}
        onClick={() => onAddRule('DELIVERY')}
        data-testid="pricing-rule-set-add-rule-target-delivery">
        {t('PRICING_ADD_RULE')}
      </Button>
      <HelpIcon className="ml-1" tooltipText={t('PRICING_ADD_RULE_HELP')} />
    </div>
  )
}
