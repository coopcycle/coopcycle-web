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
import { setTaskListGroupMode, openNewTaskModal, setCurrentRecurrenceRule, openNewRecurrenceRuleModal, deleteGroup, editGroup, showRecurrenceRules, appendToUnassignedTasks } from '../redux/actions'
import { selectGroups, selectStandaloneTasks, selectRecurrenceRules, selectIsRecurrenceRulesVisible, selectAreToursEnabled, selectTaskListGroupMode, selectIsTourDragging, selectOrderOfUnassignedTasks, selectUnassignedTasksLoading } from '../redux/selectors'
import { getDroppableListStyle } from '../utils'
import classNames from 'classnames'
import UnassignedTasksFilters from '../../components/UnassignedTasksFilters'

const StandaloneTasks =  ({tasks, offset}) => {
  // waiting for https://github.com/coopcycle/coopcycle-web/issues/4196 to resolve to bring this code back
  // takes into account manual sorting of issues
  // return _.map(unassignedTasksIdsOrder, (taskId, index) => {
  //   const task = tasks.find(t => t['@id'] === taskId)
  //   if (task) {
  //     return <Task task={ task } draggableIndex={ (offset + index) } key={ task['@id'] } />
  //   }
  // })
  return _.map(tasks, (task, index) => {
      return <Task taskId={ task['@id'] } draggableIndex={ (offset + index) } key={ task['@id'] } />
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
  const unassignedTasksIdsOrder = useSelector(selectOrderOfUnassignedTasks)
  const unassignedTasksLoading = useSelector(selectUnassignedTasksLoading)

  useEffect(() => {
    const tasksToAppend = _.filter(standaloneTasks, t => !unassignedTasksIdsOrder.includes(t['@id']))
    const tasksToAppendIds = tasksToAppend.map(t => t['@id'])

    const standaloneTaskIds = standaloneTasks.map(t => t['@id'])
    let taskToRemoveIds = _.filter(unassignedTasksIdsOrder, taskId => !standaloneTaskIds.includes(taskId))

    if (tasksToAppendIds.length > 0 || taskToRemoveIds.length > 0) {
      dispatch(appendToUnassignedTasks({tasksToAppendIds, taskToRemoveIds}))
    }

  }, [standaloneTasks]);

  return (
    <div className="dashboard__panel">
      <div className="dashboard__panel__header">
        <div className="row">
          <div className="col-md-6 col-sm-12">
            <h4><span>{ t('DASHBOARD_UNASSIGNED') }</span></h4>
          </div>
          <div className="col-md-6 col-sm-12">
            <h4 className="pull-right"><Buttons /></h4>
          </div>
        </div>
        <div>
          <UnassignedTasksFilters />
        </div>
      </div>
      <div
        className="dashboard__panel__scroll"
        style={{ opacity: unassignedTasksLoading ? 0.7 : 1, pointerEvents: unassignedTasksLoading ? 'none' : 'initial' }}
      >
        { isRecurrenceRulesVisible && recurrenceRules.map((rrule, index) =>
          <RecurrenceRule
            key={ `rrule-${index}` }
            rrule={ rrule }
            onClick={ () => dispatch(setCurrentRecurrenceRule(rrule)) } />
        ) }
          {/*
            groups are in another droppable area to ease writing the code for sorting tasks with dragNdrop
            it is a little bit ugly to have two droppable areas one after another but we assume either the coop is foodtech either it is lasmile and they have tours enabled
            also for now we can only drag out of this zone so no need to have it if there is no group in it
          */}
          { !toursEnabled && groups.length > 0 ?
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
                        <Draggable key={ `group-${group.id}` } draggableId={ `group:${group['@id']}` } index={ index }>
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
