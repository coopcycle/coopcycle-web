import React from 'react'
import { render } from 'react-dom'
import _ from 'lodash'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import { Draggable, Droppable } from "react-beautiful-dnd"
import { Popover } from 'antd'

import Task from './Task'
import TaskGroup from './TaskGroup'
import UnassignedTasksPopoverContent from './UnassignedTasksPopoverContent'
import { setTaskListGroupMode, openNewTaskModal, closeNewTaskModal, toggleSearch } from '../redux/actions'
import { selectGroups, selectStandaloneTasks } from '../redux/selectors'

const UnassignedTasksPopoverContentWithTrans = withTranslation()(UnassignedTasksPopoverContent)

class UnassignedTasks extends React.Component {

  constructor (props) {
    super(props)
    this.state = {
      popoverVisible: false
    }
  }

  renderGroup(group, tasks) {
    return (
      <TaskGroup key={ group.id } group={ group } tasks={ tasks } />
    )
  }

  render() {

    const classNames = ['dashboard__panel']
    if (this.props.hidden) {
      classNames.push('hidden')
    }

    return (
      <div className={ classNames.join(' ') }>
        <h4>
          <span>{ this.props.t('DASHBOARD_UNASSIGNED') }</span>
          <span className="pull-right">
            <a href="#" onClick={ e => {
              e.preventDefault()
              this.props.openNewTaskModal()
            }}>
              <i className="fa fa-plus"></i>
            </a>
            &nbsp;&nbsp;
            <a href="#" onClick={ e => {
              e.preventDefault()
              this.props.toggleSearch()
            }}>
              <i className="fa fa-search"></i>
            </a>
            &nbsp;&nbsp;
            <Popover
              placement="leftTop"
              arrowPointAtCenter
              title="Notifications"
              trigger="click"
              content={ <UnassignedTasksPopoverContentWithTrans
                defaultValue={ this.props.taskListGroupMode }
                onChange={ mode => {
                  this.props.setTaskListGroupMode(mode)
                  this.setState({ popoverVisible: false })
                }} />
              }
              visible={ this.state.popoverVisible }
              onVisibleChange={ value => this.setState({ popoverVisible: value }) }
            >
              <a href="#" onClick={ e => e.preventDefault() } title={ this.props.t('ADMIN_DASHBOARD_DISPLAY') }>
                <i className="fa fa-list"></i>
              </a>
            </Popover>
          </span>
        </h4>
        <div className="dashboard__panel__scroll">
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
                          { this.renderGroup(group, group.tasks) }
                        </div>
                      )}
                    </Draggable>
                  )
                })}
                { _.map(this.props.standaloneTasks, (task, index) => {
                  return (
                    <Draggable key={ task['@id'] } draggableId={ task['@id'] } index={ (this.props.groups.length + index) }>
                      {(provided, snapshot) => {

                        return (
                          <div
                            ref={ provided.innerRef }
                            { ...provided.draggableProps }
                            { ...provided.dragHandleProps }
                          >
                            <Task task={ task } />
                            { (snapshot.isDragging && this.props.selectedTasks.length > 1) && (
                              <div style={{ position: 'absolute', top: '-10px', right: '-10px', backgroundColor: '#e67e22', color: 'white', height: '20px', width: '20px', borderRadius: '50%', textAlign: 'center' }}>
                                <span style={{ lineHeight: '20px', fontWeight: '700' }}>{ this.props.selectedTasks.length }</span>
                              </div>
                            ) }
                          </div>
                        )
                      }}
                    </Draggable>
                  )
                })}
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
    taskListGroupMode: state.taskListGroupMode,
    selectedTasks: state.selectedTasks,
  }
}

function mapDispatchToProps(dispatch) {
  return {
    setTaskListGroupMode: (mode) => dispatch(setTaskListGroupMode(mode)),
    openNewTaskModal: () => dispatch(openNewTaskModal()),
    closeNewTaskModal: () => dispatch(closeNewTaskModal()),
    toggleSearch: () => dispatch(toggleSearch())
  }
}

export default connect(mapStateToProps, mapDispatchToProps, null, { forwardRef: true })(withTranslation(['common'], { withRef: true })(UnassignedTasks))
