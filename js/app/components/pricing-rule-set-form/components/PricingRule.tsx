import React, { useState, useEffect } from 'react'
import { Card, Input, Button, Space, Typography, Row, Col, Alert } from 'antd'
import { DeleteOutlined } from '@ant-design/icons'
import { useTranslation } from 'react-i18next'
import PricingRuleTarget from './PricingRuleTarget'
import RulePicker from './RulePicker'
import { PriceChoice } from './PriceChoice'
import PriceEditor from './PriceEditor'
import {
  FixedPrice,
  parsePriceAST,
  PercentagePrice,
  PricePerPackage,
  PriceRange,
} from '../../../delivery/pricing/pricing-rule-parser'
import Position from './Position'
import { DraggableProvidedDragHandleProps } from '@hello-pangea/dnd'
import { PricingRuleType } from '../types/PricingRuleType'
import HelpIcon from '../../HelpIcon'

const { Text } = Typography

export const VALIDATION_ERRORS = {
  EXPRESSION_REQUIRED: 'EXPRESSION_REQUIRED',
  PRICE_REQUIRED: 'PRICE_REQUIRED',
}

type Props = {
  rule: PricingRuleType
  index: number
  onUpdate: (rule: PricingRuleType) => void
  onRemove: () => void
  validationErrors?: string[]
  dragHandleProps: DraggableProvidedDragHandleProps
  isDragging?: boolean
}

const PricingRule = ({
  rule,
  index,
  onUpdate,
  onRemove,
  validationErrors = [],
  dragHandleProps,
  isDragging = false,
}: Props) => {
  const { t } = useTranslation()
  const [localRule, setLocalRule] = useState(rule)

  const [priceObj, setPriceObj] = useState(() => {
    return rule.priceAst
      ? parsePriceAST(rule.priceAst, rule.price)
      : new FixedPrice(0)
  })
  const [priceType, setPriceType] = useState(() => {
    let priceType = 'fixed'
    if (priceObj instanceof PercentagePrice) {
      priceType = 'percentage'
    } else if (priceObj instanceof PriceRange) {
      priceType = 'range'
    } else if (priceObj instanceof PricePerPackage) {
      priceType = 'per_package'
    }
    return priceType
  })

  useEffect(() => {
    setLocalRule(rule)
  }, [rule])

  const handleFieldChange = (field, value) => {
    const updatedRule = { ...localRule, [field]: value }
    setLocalRule(updatedRule)
    onUpdate(updatedRule)
  }

  const handleNameChange = value => {
    // Update the name
    const updatedRule = {
      ...localRule,
      name: value,
    }
    setLocalRule(updatedRule)
    onUpdate(updatedRule)
  }

  const handlePriceTypeChange = type => {
    setPriceType(type)
    let newPrice = ''

    switch (type) {
      case 'percentage':
        newPrice = 'price_percentage(0)'
        break
      case 'range':
        newPrice = 'price_range(distance, 0, 0, 0)'
        break
      case 'per_package':
        newPrice = 'price_per_package(packages, "", 0, 0, 0)'
        break
      case 'fixed':
      default:
        newPrice = '0'
        break
    }

    handleFieldChange('price', newPrice)
  }

  return (
    <Card
      data-testid={`pricing-rule-set-rule-${index}`}
      size="small"
      className={`mb-3 pricing-rule-set__rule__card ${isDragging ? 'dragging' : ''}`}
      title={
        <Text strong className="pricing-rule-set__rule__text">
          {localRule.name || ''}
        </Text>
      }
      extra={
        <Space>
          <Button
            data-testid="rule-remove"
            size="small"
            danger
            icon={<DeleteOutlined />}
            onClick={onRemove}
          />
        </Space>
      }>
      <Row gutter={16} className="pricing-rule-set__rule__border_bottom">
        <Col>
          <div
            {...dragHandleProps}
            className="pricing-rule-set__rule__handle"
            title={t('FORM_PRICING_RULE_REORDER')}>
            <div className="d-flex flex-column align-items-center">
              <Position position={index} />
              <i className="fa fa-2x fa-arrows"></i>
            </div>
          </div>
        </Col>
        <Col flex="auto">
          <Row className="mb-3">
            <Text className="pricing-rule-set__rule__text">
              {t('FORM_PRICING_RULE_SET_PRICING_RULE_NAME_LABEL')}
              <HelpIcon
                className="ml-1"
                tooltipText={t('FORM_PRICING_RULE_SET_PRICING_RULE_NAME_HELP')}
              />
            </Text>
            <Input
              data-testid="rule-name"
              value={localRule.name || ''}
              onChange={e => handleNameChange(e.target.value)}
              placeholder={t(
                'FORM_PRICING_RULE_SET_PRICING_RULE_NAME_PLACEHOLDER',
              )}
              className="mt-1 ml-2"
            />
          </Row>

          <div className="mb-3">
            <PricingRuleTarget
              className="pricing-rule-set__rule__text"
              target={localRule.target}
            />

            <RulePicker
              ruleTarget={localRule.target}
              expressionAST={localRule.expressionAst}
              onExpressionChange={newExpression => {
                if (localRule.expression === newExpression) return
                handleFieldChange('expression', newExpression)
              }}
            />

            {validationErrors.includes(
              VALIDATION_ERRORS.EXPRESSION_REQUIRED,
            ) ? (
              <Alert
                message={t('FORM_PRICING_RULE_EXPRESSION_REQUIRED')}
                type="error"
                size="small"
                className="mt-2"
                showIcon
              />
            ) : null}
          </div>
        </Col>
      </Row>
      <Row gutter={16} className="mt-2 pricing-rule-set__rule__price">
        <Col>
          <PriceChoice
            priceType={priceType}
            handlePriceTypeChange={handlePriceTypeChange}
          />
        </Col>
        <Col flex="auto">
          <PriceEditor
            priceType={priceType}
            defaultValue={priceObj}
            onChange={newPrice => {
              if (localRule.price === newPrice) return
              handleFieldChange('price', newPrice)
            }}
          />

          {validationErrors.includes(VALIDATION_ERRORS.PRICE_REQUIRED) ? (
            <Alert
              message={t('FORM_PRICING_RULE_PRICE_REQUIRED')}
              type="error"
              size="small"
              className="mt-2"
              showIcon
            />
          ) : null}
        </Col>
      </Row>
    </Card>
  )
}

export default PricingRule
