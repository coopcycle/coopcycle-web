import React from 'react'
import { Button, Alert, Typography } from 'antd'
import { DragDropContext, Droppable, Draggable } from '@hello-pangea/dnd'
import PricingRule from '../PricingRule'
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
  const handleDragEnd = result => {
    if (!result.destination) {
      return
    }

    const sourceIndex = result.source.index
    const destinationIndex = result.destination.index

    if (sourceIndex !== destinationIndex) {
      moveRuleWithinTarget(sourceIndex, destinationIndex, target)
    }
  }

  return (
    <div className="mb-4">
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
                  const globalIndex = getGlobalIndex(localIndex, target)
                  return (
                    <Draggable
                      key={`${target.toLowerCase()}-${globalIndex}`}
                      draggableId={`${target.toLowerCase()}-${globalIndex}`}
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
                            index={globalIndex}
                            onUpdate={updatedRule =>
                              updateRule(globalIndex, updatedRule)
                            }
                            onRemove={() => removeRule(globalIndex)}
                            validationErrors={
                              ruleValidationErrors[globalIndex] || []
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
