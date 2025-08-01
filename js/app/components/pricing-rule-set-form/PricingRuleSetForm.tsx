import React, { useState, useEffect, useMemo } from 'react'
import {
  Form,
  Input,
  Radio,
  Button,
  Alert,
  Spin,
  message,
  Space,
  Typography,
  Divider,
} from 'antd'
import { useTranslation } from 'react-i18next'
import { v4 as uuidv4 } from 'uuid'
import {
  useGetPricingRuleSetQuery,
  useCreatePricingRuleSetMutation,
  useUpdatePricingRuleSetMutation,
} from '../../api/slice'
import { VALIDATION_ERRORS } from './components/PricingRule'
import ShowApplications from '../Applications'
import LegacyPricingRulesWarning from './components/LegacyPricingRulesWarning'
import PricingRuleSection from './components/PricingRuleSection'

import './pricing-rule-set-form.scss'
import HelpIcon from '../HelpIcon'
import { PricingRuleType } from './types/PricingRuleType'

const { Title } = Typography

// Utility function to generate temporary @id for new rules
const generateTempId = () => `temp-${uuidv4()}`

// Check if an @id is temporary (not from backend)
const isTempId = id => typeof id === 'string' && id.startsWith('temp-')

type Props = {
  ruleSetId: number | null
  isNew?: boolean
}

const PricingRuleSetForm = ({ ruleSetId, isNew = false }: Props) => {
  const { t } = useTranslation()
  const [form] = Form.useForm()
  const [rules, setRules] = useState([] as PricingRuleType[])
  const [ruleValidationErrors, setRuleValidationErrors] = useState(
    {} as {
      [ruleId: string]: string[]
    },
  )

  // Rules by target type
  const legacyRules = useMemo(() => {
    return rules.filter(rule => rule.target === 'LEGACY_TARGET_DYNAMIC')
  }, [rules])

  const taskRules = useMemo(() => {
    return rules.filter(rule => rule.target === 'TASK')
  }, [rules])

  const deliveryRules = useMemo(() => {
    return rules.filter(rule => rule.target === 'DELIVERY')
  }, [rules])

  // Ordered rules list: legacy, task, delivery
  const orderedRules = useMemo(() => {
    return [...legacyRules, ...taskRules, ...deliveryRules]
  }, [legacyRules, taskRules, deliveryRules])

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

  // Initialize form when data is loaded
  useEffect(() => {
    if (ruleSet && !isNew) {
      try {
        form.setFieldsValue({
          name: ruleSet.name || '',
          strategy: ruleSet.strategy || 'find',
          options: Array.isArray(ruleSet.options) ? ruleSet.options : [],
        })
        setRules(ruleSet.rules)
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
    const newRuleValidationErrors = {} as { [ruleId: string]: string[] }

    orderedRules.forEach((rule, index) => {
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
        newRuleValidationErrors[rule['@id']] = ruleErrors
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
        rules: orderedRules.map((rule, index) => {
          // Remove temporary @id for new rules before sending to backend
          const cleanRule = { ...rule, position: index }
          if (isTempId(cleanRule['@id'])) {
            delete cleanRule['@id']
          }
          return cleanRule
        }),
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
      '@id': generateTempId(),
      target,
      expression: '',
      price: '',
      position: orderedRules.length,
      name: null,
    }

    // Add the rule and let the ordering be handled by the orderedRules computed property
    setRules([...rules, newRule])
  }

  const updateRule = (ruleId, updatedRule) => {
    // Find the rule by @id and update it
    const originalIndex = rules.findIndex(rule => rule['@id'] === ruleId)

    if (originalIndex !== -1) {
      const newRules = [...rules]
      newRules[originalIndex] = { ...updatedRule, '@id': ruleId }
      setRules(newRules)
    }

    // Clear validation errors for this rule when it's updated
    if (ruleValidationErrors[ruleId]) {
      const newValidationErrors = { ...ruleValidationErrors }
      delete newValidationErrors[ruleId]
      setRuleValidationErrors(newValidationErrors)
    }
  }

  const removeRule = ruleId => {
    // Find the rule by @id and remove it
    const newRules = rules.filter(rule => rule['@id'] !== ruleId)
    setRules(newRules)
  }

  const moveRule = (fromIndex, toIndex) => {
    const newOrderedRules = [...orderedRules]
    const [movedRule] = newOrderedRules.splice(fromIndex, 1)
    newOrderedRules.splice(toIndex, 0, movedRule)

    // Update the rules state to match the new order
    setRules(newOrderedRules)
  }

  // Helper function to get the global index of a rule by @id
  const getGlobalIndexById = ruleId => {
    return orderedRules.findIndex(rule => rule['@id'] === ruleId)
  }

  // Helper function to move rules within the same target group using @id
  const moveRuleWithinTarget = (fromRuleId, toRuleId, target) => {
    const globalFromIndex = getGlobalIndexById(fromRuleId)
    const globalToIndex = getGlobalIndexById(toRuleId)

    if (globalFromIndex !== -1 && globalToIndex !== -1) {
      moveRule(globalFromIndex, globalToIndex)
    }
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
        <div className="mb-4">
          <ShowApplications
            objectId={ruleSetId}
            fetchUrl="_api_/pricing_rule_sets/{id}/applications_get"
          />
        </div>
      )}

      <Form
        data-testid="pricing-rule-set-form"
        form={form}
        layout="vertical"
        onFinish={handleSubmit}
        initialValues={{
          strategy: 'find',
          options: [],
        }}>
        <Form.Item
          name="name"
          label={t('FORM_PRICING_RULE_SET_NAME_LABEL')}
          rules={[{ required: true, message: t('FORM_REQUIRED') }]}>
          <Input />
        </Form.Item>

        <Form.Item
          name="strategy"
          label={
            <>
              {t('PRICING_PRICING_RULE_SET_STRATEGY_LABEL')}
              <HelpIcon
                className="ml-1"
                tooltipText={t('FORM_PRICING_RULE_SET_STRATEGY_HELP')}
                docsPath="/en/admin/pricing_method_of_calculation"
              />
            </>
          }
          rules={[{ required: true }]}>
          <Radio.Group>
            <Space direction="vertical">
              <Radio value="find">
                {t('PRICING_PRICING_RULE_SET_STRATEGY_FIND_LABEL')}
              </Radio>
              <Radio value="map">
                {t('PRICING_PRICING_RULE_SET_STRATEGY_MAP_LABEL')}
              </Radio>
            </Space>
          </Radio.Group>
        </Form.Item>

        <Divider />

        <Form.Item
          className="pricing-rule-set"
          label={
            <>
              {t('FORM_PRICING_RULE_SET_RULES_LABEL')}
              <HelpIcon
                className="ml-1"
                tooltipText={t('PRICING_PRICING_RULE_HELP')}
                docsPath="/en/admin/pricing_rule/"
              />
            </>
          }>
          <>
            {orderedRules.length === 0 && (
              <Alert
                message={t('FORM_PRICING_RULE_SET_NO_RULE_FOUND')}
                type="warning"
                className="mb-2"
              />
            )}

            {legacyRules.length > 0 ? (
              // Legacy Rules Section
              <div>
                <Title level={5}>{t('RULE_LEGACY_TARGET_DYNAMIC_TITLE')}</Title>
                <Form.Item className="m-0">
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
                </Form.Item>
                <PricingRuleSection
                  target="LEGACY_TARGET_DYNAMIC"
                  rules={legacyRules}
                  title={null}
                  emptyMessage={t('RULE_LEGACY_TARGET_DYNAMIC_HELP')}
                  addRuleButtonLabel={t('PRICING_ADD_RULE_LEGACY')}
                  addRuleButtonHelp={t('RULE_LEGACY_TARGET_DYNAMIC_HELP')}
                  getGlobalIndexById={getGlobalIndexById}
                  updateRule={updateRule}
                  removeRule={removeRule}
                  moveRuleWithinTarget={moveRuleWithinTarget}
                  ruleValidationErrors={ruleValidationErrors}
                  onAddRule={addRule}
                />
              </div>
            ) : (
              <>
                {/* Per Point Rules Section */}
                <PricingRuleSection
                  target="TASK"
                  rules={taskRules}
                  title={t('RULE_TARGET_TASK_TITLE')}
                  emptyMessage={t('PRICING_ADD_RULE_PER_TASK_HELP')}
                  addRuleButtonLabel={t('PRICING_ADD_RULE_PER_TASK')}
                  addRuleButtonHelp={t('PRICING_ADD_RULE_PER_TASK_HELP')}
                  getGlobalIndexById={getGlobalIndexById}
                  updateRule={updateRule}
                  removeRule={removeRule}
                  moveRuleWithinTarget={moveRuleWithinTarget}
                  ruleValidationErrors={ruleValidationErrors}
                  onAddRule={addRule}
                />

                <Divider />

                {/* Per Order Rules Section */}
                <PricingRuleSection
                  target="DELIVERY"
                  rules={deliveryRules}
                  title={t('RULE_TARGET_DELIVERY_TITLE')}
                  emptyMessage={t('PRICING_ADD_RULE_HELP')}
                  addRuleButtonLabel={t('PRICING_ADD_RULE')}
                  addRuleButtonHelp={t('PRICING_ADD_RULE_HELP')}
                  getGlobalIndexById={getGlobalIndexById}
                  updateRule={updateRule}
                  removeRule={removeRule}
                  moveRuleWithinTarget={moveRuleWithinTarget}
                  ruleValidationErrors={ruleValidationErrors}
                  onAddRule={addRule}
                />
              </>
            )}
          </>
        </Form.Item>

        <Divider className="mt-5" />

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
