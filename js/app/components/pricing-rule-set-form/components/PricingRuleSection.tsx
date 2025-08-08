import { Button, Alert, Typography } from 'antd'
import { PlusOutlined } from '@ant-design/icons'
import { useTranslation } from 'react-i18next'
import DraggableRulesList from './DraggableRulesList'
import { PricingRule as PricingRuleType } from '../../../api/types'

const { Title } = Typography

type Props = {
  target: string
  rules: PricingRuleType[]
  helpMessage: string
  addRuleButtonLabel: string
  getGlobalIndexById: (ruleId: string) => number
  updateRule: (ruleId: string, updatedRule: PricingRuleType) => void
  removeRule: (ruleId: string) => void
  moveRuleWithinTarget: (
    fromRuleId: string,
    toRuleId: string,
    target: string,
  ) => void
  ruleValidationErrors: { [ruleId: string]: string[] }
  onAddRule: (target: string, isManualSupplement?: boolean) => void
  manualSupplementRules?: PricingRuleType[]
}

const PricingRuleSection = ({
  target,
  rules,
  helpMessage,
  addRuleButtonLabel,
  getGlobalIndexById,
  updateRule,
  removeRule,
  moveRuleWithinTarget,
  ruleValidationErrors,
  onAddRule,
  manualSupplementRules = undefined,
}: Props) => {
  const { t } = useTranslation()

  const handleDragEnd = (fromRuleId: string, toRuleId: string): void => {
    moveRuleWithinTarget(fromRuleId, toRuleId, target)
  }

  return (
    <div data-testid={`pricing-rule-set-target-${target.toLowerCase()}`}>
      <Alert message={helpMessage} type="info" className="mb-3" showIcon />

      <DraggableRulesList
        rules={rules}
        droppableId={`pricing-rules-${target.toLowerCase()}`}
        droppableType="pricing-rule"
        onDragEnd={handleDragEnd}
        getGlobalIndexById={getGlobalIndexById}
        updateRule={updateRule}
        removeRule={removeRule}
        ruleValidationErrors={ruleValidationErrors}
        isManualSupplement={false}
      />

      <div>
        <Button
          icon={<PlusOutlined />}
          onClick={() => onAddRule(target, false)}
          data-testid={`pricing-rule-set-add-rule-target-${target.toLowerCase()}`}>
          {addRuleButtonLabel}
        </Button>
      </div>

      {/* Manual Supplements Sub-section */}
      {manualSupplementRules !== undefined && (
        <>
          <div className="mt-4 mb-3">
            <Title level={5} className="mb-2">
              {t('PRICING_RULE_SET_MANUAL_SUPPLEMENTS')}
            </Title>
          </div>
          <Alert
            message={t('PRICING_RULE_SET_MANUAL_SUPPLEMENTS_HELP')}
            type="info"
            className="mb-3"
            showIcon
          />
          <DraggableRulesList
            rules={manualSupplementRules}
            droppableId={`manual-supplements-${target.toLowerCase()}`}
            droppableType="manual-supplement"
            onDragEnd={handleDragEnd}
            getGlobalIndexById={getGlobalIndexById}
            updateRule={updateRule}
            removeRule={removeRule}
            ruleValidationErrors={ruleValidationErrors}
            isManualSupplement={true}
          />
          <div>
            <Button
              icon={<PlusOutlined />}
              onClick={() => onAddRule(target, true)}
              data-testid={`pricing-rule-set-add-rule-target-${target.toLowerCase()}`}>
              {t('PRICING_RULE_SET_ADD_MANUAL_SUPPLEMENT')}
            </Button>
          </div>
        </>
      )}
    </div>
  )
}

export default PricingRuleSection
