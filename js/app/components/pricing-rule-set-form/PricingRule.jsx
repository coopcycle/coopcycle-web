import React, { useState, useEffect } from 'react'
import { Card, Input, Button, Space, Typography, Row, Col, Alert } from 'antd'
import {
  DeleteOutlined,
  ArrowUpOutlined,
  ArrowDownOutlined,
  DragOutlined,
} from '@ant-design/icons'
import { useTranslation } from 'react-i18next'
import PricingRuleTarget from './components/PricingRuleTarget'
import RulePicker from './components/RulePicker'
import { PriceChoice } from './components/PriceChoice'
import PriceEditor from './components/PriceEditor'
import {
  FixedPrice,
  parsePriceAST,
  PercentagePrice,
  PricePerPackage,
  PriceRange,
} from '../../delivery/pricing/pricing-rule-parser'
import Position from './components/Position'

const { Text } = Typography

export const VALIDATION_ERRORS = {
  EXPRESSION_REQUIRED: 'EXPRESSION_REQUIRED',
  PRICE_REQUIRED: 'PRICE_REQUIRED',
}

const PricingRule = ({
  rule,
  index,
  onUpdate,
  onRemove,
  onMoveUp,
  onMoveDown,
  validationErrors = [],
}) => {
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
    // Update the productOption name
    const updatedRule = {
      ...localRule,
      productOption: {
        ...localRule.productOption,
        name: value,
      },
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
      size="small"
      style={{ marginBottom: 16 }}
      title={
        <Space>
          <DragOutlined />
          <Position position={index} />
        </Space>
      }
      extra={
        <Space>
          {onMoveUp && (
            <Button
              size="small"
              icon={<ArrowUpOutlined />}
              onClick={onMoveUp}
            />
          )}
          {onMoveDown && (
            <Button
              size="small"
              icon={<ArrowDownOutlined />}
              onClick={onMoveDown}
            />
          )}
          <Button
            size="small"
            danger
            icon={<DeleteOutlined />}
            onClick={onRemove}
          />
        </Space>
      }>
      <Row gutter={16}>
        <Col>
          <div style={{ marginBottom: 16 }}>
            <Text strong>
              {t('FORM_PRICING_RULE_SET_PRICING_RULE_NAME_LABEL')}
            </Text>
            <Input
              value={localRule.productOption?.name || ''}
              onChange={e => handleNameChange(e.target.value)}
              placeholder={t(
                'FORM_PRICING_RULE_SET_PRICING_RULE_NAME_PLACEHOLDER',
              )}
              style={{ marginTop: 4 }}
            />
          </div>

          <div style={{ marginBottom: 16 }}>
            <PricingRuleTarget target={localRule.target} />

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
                style={{ marginTop: 8 }}
                showIcon
              />
            ) : null}
          </div>
        </Col>
      </Row>
      <Row gutter={16}>
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
              style={{ marginTop: 8 }}
              showIcon
            />
          ) : null}
        </Col>
      </Row>
    </Card>
  )
}

export default PricingRule
