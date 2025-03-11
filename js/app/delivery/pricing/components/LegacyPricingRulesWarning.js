import React from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from 'antd'
import Alert from '../../../components/core/Alert'

export default function LegacyPricingRulesWarning({ migrateToTarget }) {
  const { t } = useTranslation()

  return (
    <Alert info icon="info">
      <div className="ml-2 d-flex flex-column">
        <div>{t('RULE_LEGACY_TARGET_DYNAMIC_MIGRATE_RATIONALE')}</div>
        <div className="mt-4 d-flex flex-column gap-4">
          <div className="d-flex flex-column gap-2">
            <Button type="primary" onClick={() => migrateToTarget('DELIVERY')}>
              {t('RULE_LEGACY_TARGET_DYNAMIC_MIGRATE_TARGET_DELIVERY')}
            </Button>
            {t('RULE_LEGACY_TARGET_DYNAMIC_MIGRATE_TARGET_DELIVERY_HELP')}
          </div>
          <div className="d-flex flex-column gap-2">
            <Button onClick={() => migrateToTarget('TASK')}>
              {t('RULE_LEGACY_TARGET_DYNAMIC_MIGRATE_TARGET_TASK')}
            </Button>
            {t('RULE_LEGACY_TARGET_DYNAMIC_MIGRATE_TARGET_TASK_HELP')}
          </div>
        </div>
      </div>
    </Alert>
  )
}
