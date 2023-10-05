import React, { useState } from 'react'
import _ from 'lodash'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import { Draggable, Droppable } from "react-beautiful-dnd"
import { Popover } from 'antd'
import { useTranslation } from 'react-i18next'

import Task from './Task'
import TaskGroup from './TaskGroup'
import RecurrenceRule from './RecurrenceRule'
import UnassignedTasksPopoverContent from './UnassignedTasksPopoverContent'
import { setTaskListGroupMode, openNewTaskModal, toggleSearch, setCurrentRecurrenceRule, openNewRecurrenceRuleModal, deleteGroup, editGroup, showRecurrenceRules } from '../redux/actions'
import { selectGroups, selectStandaloneTasks, selectRecurrenceRules, selectSelectedTasks } from '../redux/selectors'

class StandaloneTasks extends React.Component {

  shouldComponentUpdate(nextProps) {
    if (nextProps.tasks === this.props.tasks
      && nextProps.offset === this.props.offset) {
      return false
    }

    return true
  }

  render() {
    return _.map(this.props.tasks, (task, index) => {

      return (
        <Draggable key={ task['@id'] } draggableId={ task['@id'] } index={ (this.props.offset + index) }>
          {(provided, snapshot) => {

            return (
              <div
                ref={ provided.innerRef }
                { ...provided.draggableProps }
                { ...provided.dragHandleProps }
              >
                <Task task={ task } />
                { (snapshot.isDragging && this.props.selectedTasksLength > 1) && (
                  <div className="task-dragging-number">
                    <span>{ this.props.selectedTasksLength }</span>
                  </div>
                ) }
              </div>
            )
          }}
        </Draggable>
      )
    })
  }
}

const StandaloneTasksWithConnect = connect(
  (state) => ({
    selectedTasksLength: selectSelectedTasks(state).length,
  })
)(StandaloneTasks)

const Buttons = connect(
  (state) => ({
    taskListGroupMode: state.taskListGroupMode,
    isRecurrenceRulesVisible: state.settings.isRecurrenceRulesVisible,
  }),
  (dispatch) => ({
    setTaskListGroupMode: (mode) => dispatch(setTaskListGroupMode(mode)),
    openNewTaskModal: () => dispatch(openNewTaskModal()),
    toggleSearch: () => dispatch(toggleSearch()),
    openNewRecurrenceRuleModal: () => dispatch(openNewRecurrenceRuleModal()),
    showRecurrenceRules: (isChecked) =>dispatch(showRecurrenceRules(isChecked))
  })
)(({ taskListGroupMode, setTaskListGroupMode, openNewTaskModal, toggleSearch, openNewRecurrenceRuleModal, isRecurrenceRulesVisible, showRecurrenceRules }) => {

  const [ visible, setVisible ] = useState(false)
  const { t } = useTranslation()

  return (
    <React.Fragment>
      <a href="#" className="mr-3" onClick={ e => {
        e.preventDefault()
        openNewRecurrenceRuleModal()
      }}>
        <i className="fa fa-clock-o"></i>
      </a>
      <a href="#" className="mr-3" onClick={ e => {
        e.preventDefault()
        openNewTaskModal()
      }}>
        <i className="fa fa-plus"></i>
      </a>
      <a href="#" className="mr-3" onClick={ e => {
        e.preventDefault()
        toggleSearch()
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
            setTaskListGroupMode(mode)
            setVisible(false)
          }}
          isRecurrenceRulesVisible={isRecurrenceRulesVisible}
          showRecurrenceRules={showRecurrenceRules}
           />
        }
        open={ visible }
        onOpenChange={ value => setVisible(value) }
      >
        <a href="#" onClick={ e => e.preventDefault() } title={ t('ADMIN_DASHBOARD_DISPLAY') }>
          <i className="fa fa-list"></i>
        </a>
      </Popover>
    </React.Fragment>
  )
})

class UnassignedTasks extends React.Component {

  render() {

    return (
      <div className="dashboard__panel">
        <h4 className="d-flex justify-content-between">
          <span>{ this.props.t('DASHBOARD_UNASSIGNED') }</span>
          <span>
            <Buttons />
          </span>
        </h4>
        <div className="dashboard__panel__scroll">
          { this.props.isRecurrenceRulesVisible && this.props.recurrenceRules.map((rrule, index) =>
            <RecurrenceRule
              key={ `rrule-${index}` }
              rrule={ rrule }
              onClick={ () => this.props.setCurrentRecurrenceRule(rrule) } />
          ) }
          <Droppable droppableId="unassigned">
            {(provided) => (
              <div className="list-group nomargin" ref={ provided.innerRef } { ...provided.droppableProps }>
                { _.map(this.props.groups, (group, index) => {
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
                            onConfirmDelete={ () => this.props.deleteGroup(group) }
                            onEdit={ (data) => this.props.editGroup(data) } />
                        </div>
                      )}
                    </Draggable>
                  )
                })}

                <StandaloneTasksWithConnect
                  tasks={ this.props.standaloneTasks }
                  offset={ this.props.groups.length } />
                { provided.placeholder }
              </div>
            )}
          </Droppable>
        </div>
      </div>
    )
  }
}

function mapStateToProps (state) {

  return {
    groups: selectGroups(state),
    standaloneTasks: selectStandaloneTasks(state),
    recurrenceRules: selectRecurrenceRules(state),
    isRecurrenceRulesVisible: state.settings.isRecurrenceRulesVisible,
  }
}

function mapDispatchToProps(dispatch) {
  return {
    setCurrentRecurrenceRule: (recurrenceRule) => dispatch(setCurrentRecurrenceRule(recurrenceRule)),
    deleteGroup: (group) => dispatch(deleteGroup(group)),
    editGroup: (group) => dispatch(editGroup(group)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(UnassignedTasks))
