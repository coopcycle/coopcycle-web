import React from 'react'
import { Typography } from 'antd'
import { useTranslation } from 'react-i18next'
import { PricingRule } from '../../../../api/types'
import ManualSupplement from '../ManualSupplement'

const { Text } = Typography

type Props = {
  rules: PricingRule[]
}

export default function ManualSupplements({ rules }: Props) {
  const { t } = useTranslation()

  return (
    <div>
      <Text strong>{t('DELIVERY_FORM_SUPPLEMENTS')}</Text>
      {rules.map(rule => (
        <ManualSupplement key={rule.id} rule={rule} />
      ))}
    </div>
  )
}
