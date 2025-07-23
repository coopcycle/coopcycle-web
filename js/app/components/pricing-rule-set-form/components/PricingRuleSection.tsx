import React from 'react'
import { Button, Alert, Typography } from 'antd'
import { DragDropContext, Droppable, Draggable } from '@hello-pangea/dnd'
import PricingRule from './PricingRule'
import { PlusOutlined } from '@ant-design/icons'
import HelpIcon from '../../HelpIcon'
import { PricingRuleType } from '../types/PricingRuleType'
import { useTranslation } from 'react-i18next'

const { Title } = Typography

type Props = {
  target: string
  rules: PricingRuleType[]
  title: string | null
  emptyMessage: string
  addRuleButtonLabel: string
  addRuleButtonHelp: string
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
  title,
  emptyMessage,
  addRuleButtonLabel,
  addRuleButtonHelp,
  getGlobalIndexById,
  updateRule,
  removeRule,
  moveRuleWithinTarget,
  ruleValidationErrors,
  onAddRule,
  manualSupplementRules = undefined,
}: Props) => {
  const { t } = useTranslation()
  const handleDragEnd = result => {
    if (!result.destination) {
      return
    }

    const sourceIndex = result.source.index
    const destinationIndex = result.destination.index

    if (sourceIndex !== destinationIndex) {
      // Get the @id of the rules being moved
      const fromRuleId = rules[sourceIndex]['@id']
      const toRuleId = rules[destinationIndex]['@id']
      moveRuleWithinTarget(fromRuleId, toRuleId, target)
    }
  }

  return (
    <div data-testid={`pricing-rule-set-target-${target.toLowerCase()}`}>
      {title ? (
        <Title level={5}>
          {title} <HelpIcon className="ml-1" tooltipText={addRuleButtonHelp} />
        </Title>
      ) : null}

      {rules.length === 0 ? (
        <Alert message={emptyMessage} type="info" className="mb-3" showIcon />
      ) : (
        <DragDropContext onDragEnd={handleDragEnd}>
          <Droppable
            droppableId={`pricing-rules-${target.toLowerCase()}`}
            type="pricing-rule">
            {(provided, snapshot) => (
              <div
                {...provided.droppableProps}
                ref={provided.innerRef}
                style={{
                  backgroundColor: snapshot.isDraggingOver
                    ? '#f0f0f0'
                    : 'transparent',
                  minHeight: '20px',
                  transition: 'background-color 0.2s ease',
                }}>
                {rules.map((rule, localIndex) => {
                  const ruleId = rule['@id']
                  return (
                    <Draggable
                      key={ruleId}
                      draggableId={ruleId}
                      index={localIndex}>
                      {(provided, snapshot) => (
                        <div
                          ref={provided.innerRef}
                          {...provided.draggableProps}
                          style={{
                            ...provided.draggableProps.style,
                            opacity: snapshot.isDragging ? 0.8 : 1,
                          }}>
                          <PricingRule
                            rule={rule}
                            index={getGlobalIndexById(ruleId)}
                            onUpdate={updatedRule =>
                              updateRule(ruleId, updatedRule)
                            }
                            onRemove={() => removeRule(ruleId)}
                            validationErrors={
                              ruleValidationErrors[ruleId] || []
                            }
                            dragHandleProps={provided.dragHandleProps}
                            isDragging={snapshot.isDragging}
                          />
                        </div>
                      )}
                    </Draggable>
                  )
                })}
                {provided.placeholder}
              </div>
            )}
          </Droppable>
        </DragDropContext>
      )}

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

          {manualSupplementRules.length === 0 ? (
            <Alert
              message={t('PRICING_RULE_SET_MANUAL_SUPPLEMENTS_EMPTY')}
              type="info"
              className="mb-3"
              showIcon
            />
          ) : (
            <DragDropContext
              onDragEnd={result => {
                if (!result.destination) return
                const sourceIndex = result.source.index
                const destinationIndex = result.destination.index
                if (sourceIndex !== destinationIndex) {
                  const fromRuleId = manualSupplementRules[sourceIndex]['@id']
                  const toRuleId =
                    manualSupplementRules[destinationIndex]['@id']
                  moveRuleWithinTarget(fromRuleId, toRuleId, target)
                }
              }}>
              <Droppable
                droppableId={`manual-supplements-${target.toLowerCase()}`}
                type="manual-supplement">
                {(provided, snapshot) => (
                  <div
                    {...provided.droppableProps}
                    ref={provided.innerRef}
                    style={{
                      backgroundColor: snapshot.isDraggingOver
                        ? '#f0f0f0'
                        : 'transparent',
                      minHeight: '20px',
                      transition: 'background-color 0.2s ease',
                    }}>
                    {manualSupplementRules.map((rule, localIndex) => {
                      const ruleId = rule['@id']
                      return (
                        <Draggable
                          key={ruleId}
                          draggableId={ruleId}
                          index={localIndex}>
                          {(provided, snapshot) => (
                            <div
                              ref={provided.innerRef}
                              {...provided.draggableProps}
                              style={{
                                ...provided.draggableProps.style,
                                opacity: snapshot.isDragging ? 0.8 : 1,
                              }}>
                              <PricingRule
                                isManualSupplement
                                rule={rule}
                                index={getGlobalIndexById(ruleId)}
                                onUpdate={updatedRule =>
                                  updateRule(ruleId, updatedRule)
                                }
                                onRemove={() => removeRule(ruleId)}
                                validationErrors={
                                  ruleValidationErrors[ruleId] || []
                                }
                                dragHandleProps={provided.dragHandleProps}
                                isDragging={snapshot.isDragging}
                              />
                            </div>
                          )}
                        </Draggable>
                      )
                    })}
                    {provided.placeholder}
                  </div>
                )}
              </Droppable>
            </DragDropContext>
          )}

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
