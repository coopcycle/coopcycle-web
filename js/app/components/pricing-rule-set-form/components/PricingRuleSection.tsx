import React from 'react'
import { Button, Alert, Typography } from 'antd'
import { DragDropContext, Droppable, Draggable } from '@hello-pangea/dnd'
import PricingRule from './PricingRule'
import { PlusOutlined } from '@ant-design/icons'
import HelpIcon from '../../HelpIcon'
import { PricingRuleType } from '../types/PricingRuleType'

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
  ruleValidationErrors: any
  onAddRule: (target: string) => void
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
}: Props) => {
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
    <div>
      {title ? (
        <Title level={5}>
          {title} <HelpIcon className="ml-1" tooltipText={addRuleButtonHelp} />
        </Title>
      ) : null}

      {rules.length === 0 ? (
        <Alert message={emptyMessage} type="info" className="mb-3" showIcon />
      ) : (
        <DragDropContext onDragEnd={handleDragEnd}>
          <Droppable droppableId={`pricing-rules-${target.toLowerCase()}`}>
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
          onClick={() => onAddRule(target)}
          data-testid={`pricing-rule-set-add-rule-target-${target.toLowerCase()}`}>
          {addRuleButtonLabel}
        </Button>
      </div>
    </div>
  )
}

export default PricingRuleSection
