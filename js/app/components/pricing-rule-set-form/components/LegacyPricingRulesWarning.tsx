import React from 'react';
import { useTranslation } from 'react-i18next';
import { Button, Card } from 'antd';
import Alert from '../../../components/core/Alert';
import { PricingRuleTarget } from '../../../api/types';

type Props = {
  migrateToTarget: (target: PricingRuleTarget) => void;
};

export default function LegacyPricingRulesWarning({ migrateToTarget }: Props) {
  const { t } = useTranslation();

  return (
    <Alert info icon="info">
      <div className="ml-2 d-flex flex-column">
        <div>{t('RULE_LEGACY_TARGET_DYNAMIC_MIGRATE_RATIONALE')}</div>
        <div className="mt-4 d-flex flex-column gap-4">
          <Card>
            <Button onClick={() => migrateToTarget('DELIVERY')}>
              {t('RULE_LEGACY_TARGET_DYNAMIC_MIGRATE_TARGET_DELIVERY')}
            </Button>
            <span className="ml-2 text-muted">
              {t('RULE_LEGACY_TARGET_DYNAMIC_MIGRATE_TARGET_DELIVERY_HELP')}
            </span>
          </Card>
          <Card>
            <Button onClick={() => migrateToTarget('TASK')}>
              {t('RULE_LEGACY_TARGET_DYNAMIC_MIGRATE_TARGET_TASK')}
            </Button>
            <span className="ml-2 text-muted">
              {t('RULE_LEGACY_TARGET_DYNAMIC_MIGRATE_TARGET_TASK_HELP')}
            </span>
          </Card>
        </div>
      </div>
    </Alert>
  );
}
