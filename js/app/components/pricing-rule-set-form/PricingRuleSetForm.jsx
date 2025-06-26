import React, { useState, useEffect, useMemo } from 'react'
import { Form, Input, Radio, Button, Card, Alert, Spin, message } from 'antd'
import { useTranslation } from 'react-i18next'
import {
  useGetPricingRuleSetQuery,
  useCreatePricingRuleSetMutation,
  useUpdatePricingRuleSetMutation,
} from '../../api/slice'
import PricingRule, { VALIDATION_ERRORS } from './PricingRule'
import ShowApplications from '../Applications'
import LegacyPricingRulesWarning from './components/LegacyPricingRulesWarning'
import AddRulePerTask from './components/AddRulePerTask'
import AddRulePerDelivery from './components/AddRulePerDelivery'

const PricingRuleSetForm = ({ ruleSetId, isNew = false }) => {
  const { t } = useTranslation()
  const [form] = Form.useForm()
  const [rules, setRules] = useState([])
  const [ruleValidationErrors, setRuleValidationErrors] = useState({})

  // RTK Query hooks
  const {
    data: ruleSet,
    isLoading: isLoadingRuleSet,
    error: ruleSetError,
  } = useGetPricingRuleSetQuery(ruleSetId, {
    skip: isNew,
  })

  const [createPricingRuleSet, { isLoading: isCreating }] =
    useCreatePricingRuleSetMutation()

  const [updatePricingRuleSet, { isLoading: isUpdating }] =
    useUpdatePricingRuleSetMutation()

  const hasLegacyRules = useMemo(() => {
    return rules.some(rule => rule.target === 'LEGACY_TARGET_DYNAMIC')
  }, [rules])

  // Initialize form when data is loaded
  useEffect(() => {
    if (ruleSet && !isNew) {
      try {
        form.setFieldsValue({
          name: ruleSet.name || '',
          strategy: ruleSet.strategy || 'find',
          options: Array.isArray(ruleSet.options) ? ruleSet.options : [],
        })
        // Ensure rules is always an array
        const rulesArray = Array.isArray(ruleSet.rules) ? ruleSet.rules : []
        setRules(rulesArray)
      } catch (error) {
        console.error('Error initializing form:', error)
        // Set default values if there's an error
        form.setFieldsValue({
          name: '',
          strategy: 'find',
          options: [],
        })
        setRules([])
      }
    }
  }, [ruleSet, form, isNew])

  const validateRules = () => {
    const errors = []
    const newRuleValidationErrors = {}

    rules.forEach((rule, index) => {
      const ruleErrors = []

      // Check if expression is empty
      if (!rule.expression || rule.expression.trim() === '') {
        ruleErrors.push(VALIDATION_ERRORS.EXPRESSION_REQUIRED)
      }

      // Check if price is empty
      if (!rule.price || rule.price.trim() === '') {
        ruleErrors.push(VALIDATION_ERRORS.PRICE_REQUIRED)
      }

      if (ruleErrors.length > 0) {
        errors.push({
          index,
          errors: ruleErrors,
        })
        newRuleValidationErrors[index] = ruleErrors
      }
    })

    // Update validation errors state
    setRuleValidationErrors(newRuleValidationErrors)

    return errors
  }

  const handleSubmit = async values => {
    try {
      // Validate rules before submission
      const validationErrors = validateRules()

      if (validationErrors.length > 0) {
        // Show validation errors
        validationErrors.forEach(({ index }) => {
          message.error(
            `${t('FORM_PRICING_RULE_SAVE_ERROR', { name: `#${index + 1}` })}`,
          )
        })
        return
      }

      // Clear validation errors if validation passes
      setRuleValidationErrors({})

      const payload = {
        ...values,
        rules: rules.map((rule, index) => ({
          ...rule,
          position: index,
        })),
      }

      if (isNew) {
        const result = await createPricingRuleSet(payload).unwrap()
        message.success(t('SAVE_SUCCESS'))
        // Redirect to edit mode
        window.location.href = `/admin/deliveries/pricing/beta/${result.id}`
      } else {
        await updatePricingRuleSet({
          id: ruleSetId,
          ...payload,
        }).unwrap()
        message.success(t('SAVE_SUCCESS'))
      }
    } catch (error) {
      console.error('Failed to save pricing rule set:', error)
      message.error(t('SAVE_ERROR'))
    }
  }

  const addRule = (target = 'DELIVERY') => {
    const newRule = {
      target,
      expression: '',
      price: '',
      position: rules.length,
      productOption: null,
    }
    setRules([...rules, newRule])
  }

  const updateRule = (index, updatedRule) => {
    const newRules = [...rules]
    newRules[index] = updatedRule
    setRules(newRules)

    // Clear validation errors for this rule when it's updated
    if (ruleValidationErrors[index]) {
      const newValidationErrors = { ...ruleValidationErrors }
      delete newValidationErrors[index]
      setRuleValidationErrors(newValidationErrors)
    }
  }

  const removeRule = index => {
    const newRules = rules.filter((_, i) => i !== index)
    setRules(newRules)
  }

  const moveRule = (fromIndex, toIndex) => {
    const newRules = [...rules]
    const [movedRule] = newRules.splice(fromIndex, 1)
    newRules.splice(toIndex, 0, movedRule)
    setRules(newRules)
  }

  if (isLoadingRuleSet && !isNew) {
    return (
      <div style={{ textAlign: 'center', padding: '50px' }}>
        <Spin size="large" />
      </div>
    )
  }

  if (ruleSetError) {
    return (
      <Alert
        message="Error"
        description="Failed to load pricing rule set"
        type="error"
        showIcon
      />
    )
  }

  return (
    <div style={{ maxWidth: 1200, margin: '0 auto', padding: '24px' }}>
      {!isNew && (
        <ShowApplications
          objectId={ruleSetId}
          fetchUrl="_api_/pricing_rule_sets/{id}/applications_get"
        />
      )}

      <Form
        form={form}
        layout="vertical"
        onFinish={handleSubmit}
        initialValues={{
          strategy: 'find',
          options: [],
        }}>
        <Card title={t('BASIC_INFORMATION')} style={{ marginBottom: 24 }}>
          <Form.Item
            name="name"
            label={t('FORM_PRICING_RULE_SET_NAME_LABEL')}
            rules={[{ required: true, message: t('FORM_REQUIRED') }]}>
            <Input />
          </Form.Item>

          <Form.Item
            name="strategy"
            label={t('FORM_PRICING_RULE_SET_STRATEGY_LABEL')}
            help={t('FORM_PRICING_RULE_SET_STRATEGY_HELP')}
            rules={[{ required: true }]}>
            <Radio.Group>
              <Radio value="find">
                {t('FORM_PRICING_RULE_SET_STRATEGY_FIND_LABEL')}
              </Radio>
              <Radio value="map">
                {t('FORM_PRICING_RULE_SET_STRATEGY_MAP_LABEL')}
              </Radio>
            </Radio.Group>
          </Form.Item>
        </Card>

        {hasLegacyRules ? (
          <LegacyPricingRulesWarning
            migrateToTarget={ruleTarget => {
              setRules(
                rules.map(rule => ({
                  ...rule,
                  target: ruleTarget,
                })),
              )
            }}
          />
        ) : null}

        <Card
          title={t('FORM_PRICING_RULE_SET_RULES_LABEL')}
          style={{ marginBottom: 24 }}
          actions={[
            <AddRulePerTask onAddRule={target => addRule(target)} />,
            <AddRulePerDelivery onAddRule={target => addRule(target)} />,
          ]}>
          {rules.length === 0 && (
            <Alert
              message={t('FORM_PRICING_RULE_SET_NO_RULE_FOUND')}
              type="warning"
              style={{ marginBottom: 16 }}
            />
          )}

          {rules.map((rule, index) => (
            <PricingRule
              key={index}
              rule={rule}
              index={index}
              onUpdate={updatedRule => updateRule(index, updatedRule)}
              onRemove={() => removeRule(index)}
              onMoveUp={index > 0 ? () => moveRule(index, index - 1) : null}
              onMoveDown={
                index < rules.length - 1
                  ? () => moveRule(index, index + 1)
                  : null
              }
              validationErrors={ruleValidationErrors[index] || []}
            />
          ))}
        </Card>

        <Form.Item>
          <Button
            type="primary"
            htmlType="submit"
            size="large"
            block
            loading={isCreating || isUpdating}>
            {t('SAVE_BUTTON')}
          </Button>
        </Form.Item>
      </Form>
    </div>
  )
}

export default PricingRuleSetForm
