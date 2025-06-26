import React from 'react'
import { Alert, Typography } from 'antd'
import PricingRule from '../PricingRule'
import { Button } from '../../core/AntdButton'
import { PlusOutlined } from '@ant-design/icons'
import HelpIcon from '../../HelpIcon'

const { Title } = Typography

const PricingRuleSection = ({
  target,
  rules,
  title,
  emptyMessage,
  addRuleButtonLabel,
  addRuleButtonHelp,
  getGlobalIndex,
  updateRule,
  removeRule,
  moveRuleWithinTarget,
  ruleValidationErrors,
  onAddRule,
}) => {
  return (
    <div className="mb-4">
      {title ? <Title level={5}>{title}</Title> : null}

      {rules.length === 0 ? (
        <Alert message={emptyMessage} type="info" className="mb-3" showIcon />
      ) : (
        rules.map((rule, localIndex) => {
          const globalIndex = getGlobalIndex(localIndex, target)
          return (
            <PricingRule
              key={`${target.toLowerCase()}-${localIndex}`}
              rule={rule}
              index={globalIndex}
              onUpdate={updatedRule => updateRule(globalIndex, updatedRule)}
              onRemove={() => removeRule(globalIndex)}
              onMoveUp={
                localIndex > 0
                  ? () =>
                      moveRuleWithinTarget(localIndex, localIndex - 1, target)
                  : null
              }
              onMoveDown={
                localIndex < rules.length - 1
                  ? () =>
                      moveRuleWithinTarget(localIndex, localIndex + 1, target)
                  : null
              }
              validationErrors={ruleValidationErrors[globalIndex] || []}
            />
          )
        })
      )}

      <div className="mb-3">
        <div>
          <Button
            success
            icon={<PlusOutlined />}
            onClick={() => onAddRule(target)}
            data-testid={`pricing-rule-set-add-rule-target-${target.toLowerCase()}`}>
            {addRuleButtonLabel}
          </Button>
          <HelpIcon className="ml-1" tooltipText={addRuleButtonHelp} />
        </div>
      </div>
    </div>
  )
}

export default PricingRuleSection
