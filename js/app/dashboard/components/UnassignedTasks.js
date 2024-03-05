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
import { selectGroups, selectStandaloneTasks, selectRecurrenceRules, selectSelectedTasks, selectIsRecurrenceRulesVisible, selectAreToursEnabled, selectTaskListGroupMode } from '../redux/selectors'

const StandaloneTasks = ({tasks, offset, selectedTasksLength}) => {
  return _.map(tasks, (task, index) => {

    return (
      <Draggable key={ task['@id'] } draggableId={ task['@id'] } index={ (offset + index) }>
        {(provided, snapshot) => {

          return (
            <div
              ref={ provided.innerRef }
              { ...provided.draggableProps }
              { ...provided.dragHandleProps }
            >
              <Task task={ task } />
              { (snapshot.isDragging && selectedTasksLength > 1) && (
                <div className="task-dragging-number">
                  <span>{ selectedTasksLength }</span>
                </div>
              ) }
            </div>
          )
        }}
      </Draggable>
    )
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
          showRecurrenceRules={showRecurrenceRules}
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

const UnassignedTasks = () => {

  const dispatch = useDispatch()
  const { t } = useTranslation()

  const groups =  useSelector(selectGroups)
  const standaloneTasks = useSelector(selectStandaloneTasks)
  const recurrenceRules = useSelector(selectRecurrenceRules)
  const isRecurrenceRulesVisible = useSelector(selectIsRecurrenceRulesVisible)
  const toursEnabled = useSelector(selectAreToursEnabled)
  const selectedTasksLength = useSelector(selectSelectedTasks).length

  // not the nicest ever. when tasks changed, we want to render droppable on "next tick"
  // otherwise we may run in the error "Unable to find draggable with id: <taskId>" (then the task wont be draggable)
  // ref https://github.com/atlassian/@hello-pangea/dnd/issues/2407#issuecomment-1648339464
  const [renderDroppableArea, setRenderDroppableArea] = useState(false);
  useEffect(() => {
    const animation = requestAnimationFrame(() => setRenderDroppableArea(true));
    return () => {
      cancelAnimationFrame(animation);
      setRenderDroppableArea(false);
    };
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
        { renderDroppableArea ?
          <Droppable droppableId="unassigned">
            {(provided) => (
              <div className="list-group nomargin" ref={ provided.innerRef } { ...provided.droppableProps }>
                { !toursEnabled ? _.map(groups, (group, index) => {
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
                }) : null}

                <StandaloneTasks
                  tasks={ standaloneTasks }
                  offset={ groups.length }
                  selectedTasksLength={selectedTasksLength}
                />
                { provided.placeholder }
              </div>
            )}
          </Droppable>
          : null }
      </div>
    </div>
  )
}

export default UnassignedTasks
