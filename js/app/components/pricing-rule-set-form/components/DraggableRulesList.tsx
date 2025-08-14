import React from 'react'
import {
  DragDropContext,
  Droppable,
  Draggable,
  DropResult,
} from '@hello-pangea/dnd'
import PricingRule from './PricingRule'
import { PricingRule as PricingRuleType } from '../../../api/types'

type Props = {
  rules: PricingRuleType[]
  droppableId: string
  droppableType: string
  onDragEnd: (fromRuleId: string, toRuleId: string) => void
  getGlobalIndexById: (ruleId: string) => number
  updateRule: (ruleId: string, updatedRule: PricingRuleType) => void
  removeRule: (ruleId: string) => void
  ruleValidationErrors: { [ruleId: string]: string[] }
  isManualSupplement?: boolean
}

const DraggableRulesList = ({
  rules,
  droppableId,
  droppableType,
  onDragEnd,
  getGlobalIndexById,
  updateRule,
  removeRule,
  ruleValidationErrors,
  isManualSupplement = false,
}: Props) => {
  const handleDragEnd = (result: DropResult): void => {
    if (!result.destination) {
      return
    }

    const sourceIndex = result.source.index
    const destinationIndex = result.destination.index

    if (sourceIndex !== destinationIndex) {
      // Get the @id of the rules being moved
      const fromRuleId = rules[sourceIndex]['@id']
      const toRuleId = rules[destinationIndex]['@id']
      onDragEnd(fromRuleId, toRuleId)
    }
  }

  return (
    <DragDropContext onDragEnd={handleDragEnd}>
      <Droppable droppableId={droppableId} type={droppableType}>
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
                <Draggable key={ruleId} draggableId={ruleId} index={localIndex}>
                  {(provided, snapshot) => (
                    <div
                      ref={provided.innerRef}
                      {...provided.draggableProps}
                      style={{
                        ...provided.draggableProps.style,
                        opacity: snapshot.isDragging ? 0.8 : 1,
                      }}>
                      <PricingRule
                        isManualSupplement={isManualSupplement}
                        rule={rule}
                        index={getGlobalIndexById(ruleId)}
                        onUpdate={updatedRule =>
                          updateRule(ruleId, updatedRule)
                        }
                        onRemove={() => removeRule(ruleId)}
                        validationErrors={ruleValidationErrors[ruleId] || []}
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
  )
}

export default DraggableRulesList
