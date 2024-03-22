import React, { useEffect, useState } from 'react'
import _ from 'lodash'
import { useDispatch, useSelector } from 'react-redux'
import { Draggable, Droppable } from "@hello-pangea/dnd"
import { Popover } from 'antd'
import { useTranslation } from 'react-i18next'

import Task from './Task'
import TaskGroup from './TaskGroup'
import RecurrenceRule from './RecurrenceRule'
import UnassignedTasksPopoverContent from './UnassignedTasksPopoverContent'
import { setTaskListGroupMode, openNewTaskModal, toggleSearch, setCurrentRecurrenceRule, openNewRecurrenceRuleModal, deleteGroup, editGroup, showRecurrenceRules } from '../redux/actions'
import { selectGroups, selectStandaloneTasks, selectRecurrenceRules, selectIsRecurrenceRulesVisible, selectAreToursEnabled, selectTaskListGroupMode, selectIsTourDragging } from '../redux/selectors'
import { getDroppableListStyle } from '../utils'
import classNames from 'classnames'

const StandaloneTasks =  ({tasks, unassignedTasksIdsOrder, offset}) => {
  return _.map(unassignedTasksIdsOrder, (taskId, index) => {
    const task = tasks.find(t => t['@id'] === taskId)
    if (task) {
      return <Task task={ task } draggableIndex={ (offset + index) } key={ task['@id'] } />
    }
  })
}


const Buttons = () => {
  const [ visible, setVisible ] = useState(false)
  const { t } = useTranslation()
  const dispatch = useDispatch()

  const isRecurrenceRulesVisible = useSelector(selectIsRecurrenceRulesVisible)
  const taskListGroupMode = useSelector(selectTaskListGroupMode)

  return (
    <React.Fragment>
      <a href="#" className="mr-3" onClick={ e => {
        e.preventDefault()
        dispatch(openNewRecurrenceRuleModal())
      }}>
        <i className="fa fa-clock-o"></i>
      </a>
      <a href="#" className="mr-3" onClick={ e => {
        e.preventDefault()
        dispatch(openNewTaskModal())
      }}>
        <i className="fa fa-plus"></i>
      </a>
      <a href="#" className="mr-3" onClick={ e => {
        e.preventDefault()
        dispatch(toggleSearch())
      }}>
        <i className="fa fa-search"></i>
      </a>
      <Popover
        placement="leftTop"
        arrowPointAtCenter
        trigger="click"
        content={ <UnassignedTasksPopoverContent
          defaultValue={ taskListGroupMode }
          onChange={ mode => {
            dispatch(setTaskListGroupMode(mode))
            setVisible(false)
          }}
          isRecurrenceRulesVisible={isRecurrenceRulesVisible}
          showRecurrenceRules={(checked) => dispatch(showRecurrenceRules(checked))}
           />
        }
        open={ visible }
        onOpenChange={ value => setVisible(value)}
      >
        <a href="#" onClick={ e => e.preventDefault() } title={ t('ADMIN_DASHBOARD_DISPLAY') }>
          <i className="fa fa-list"></i>
        </a>
      </Popover>
    </React.Fragment>
  )
}

export const UnassignedTasks = () => {

  const dispatch = useDispatch()
  const { t } = useTranslation()

  const groups =  useSelector(selectGroups)
  const standaloneTasks = useSelector(selectStandaloneTasks)
  const recurrenceRules = useSelector(selectRecurrenceRules)
  const isRecurrenceRulesVisible = useSelector(selectIsRecurrenceRulesVisible)
  const toursEnabled = useSelector(selectAreToursEnabled)
  const isTourDragging = useSelector(selectIsTourDragging)
  let unassignedTasksIdsOrder = useSelector(state => state.logistics.ui.unassignedTasksIdsOrder)

  useEffect(() => {
    let tasksToAppend = _.filter(standaloneTasks, t => !unassignedTasksIdsOrder.includes(t['@id']))
    const standaloneTaskIds = standaloneTasks.map(t => t['@id'])
    let tasksToRemove = _.filter(unassignedTasksIdsOrder, taskId => !standaloneTaskIds.includes(taskId))

    if (tasksToAppend.length > 0 || tasksToAppend.length > 0) {
      dispatch({type: "APPEND_TO_UNASSIGNED_TASKS", tasksToAppend, tasksToRemove })
    }

  }, [standaloneTasks]);

  return (
    <div className="dashboard__panel">
      <h4 className="d-flex justify-content-between">
        <span>{ t('DASHBOARD_UNASSIGNED') }</span>
        <span>
          <Buttons />
        </span>
      </h4>
      <div className="dashboard__panel__scroll">
        { isRecurrenceRulesVisible && recurrenceRules.map((rrule, index) =>
          <RecurrenceRule
            key={ `rrule-${index}` }
            rrule={ rrule }
            onClick={ () => dispatch(setCurrentRecurrenceRule(rrule)) } />
        ) }
          { !toursEnabled ?
            <Droppable
              droppableId="unassigned_groups"
              isDropDisabled={true}
            >
            {(provided, snapshot) => (
              <div ref={ provided.innerRef } { ...provided.droppableProps }>
                <div
                  className={ classNames({
                    'taskList__tasks': true,
                    'list-group': true,
                    'm-0': true,
                  })}
                  style={getDroppableListStyle(snapshot.isDraggingOver)}
                >
                  {_.map(groups, (group, index) => {
                      return (
                        <Draggable key={ `group-${group.id}` } draggableId={ `group:${group.id}` } index={ index }>
                          {(provided) => (
                            <div
                              ref={ provided.innerRef }
                              { ...provided.draggableProps }
                              { ...provided.dragHandleProps }
                            >
                              <TaskGroup
                                key={ group.id }
                                group={ group }
                                tasks={ group.tasks }
                                onConfirmDelete={ () => dispatch(deleteGroup(group)) }
                                onEdit={ (data) => dispatch(editGroup(data)) } />
                            </div>
                          )}
                        </Draggable>
                      )
                    })}
                    { provided.placeholder }
                </div>
              </div>
            )}
            </Droppable> : null
          }
          <Droppable
            droppableId="unassigned"
            isDropDisabled={isTourDragging}
          >
            {(provided, snapshot) => (
              <div ref={ provided.innerRef } { ...provided.droppableProps }>
                { <div
                  className={ classNames({
                    'taskList__tasks': true,
                    'list-group': true,
                    'm-0': true,
                  }) }
                  style={getDroppableListStyle(snapshot.isDraggingOver)}
                >
                  {/* offset={ !toursEnabled ? groups.length : 0 */}
                  <StandaloneTasks
                    tasks={ standaloneTasks }
                    unassignedTasksIdsOrder={ unassignedTasksIdsOrder }
                    offset={ 0 }
                  />
                  { provided.placeholder }
                </div> }
              </div>
            )}
          </Droppable>

      </div>
    </div>
  )
}
