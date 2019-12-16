import React from 'react'
import _ from 'lodash'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import moment from 'moment'

import Task from './Task'
import TaskGroup from './TaskGroup'
import { setTaskListGroupMode, openNewTaskModal, closeNewTaskModal, toggleSearch } from '../redux/actions'
import { selectFilteredTasks } from '../redux/selectors'

class UnassignedTasks extends React.Component {

  componentDidMount() {

    const $groupModeBtn = $('#task-list-group-mode')

    $(document).on('click', '#task-list-group-mode--group', () => {
      this.props.setTaskListGroupMode('GROUP_MODE_FOLDERS')
      $groupModeBtn.popover('hide')
    })

    $(document).on('click', '#task-list-group-mode--none', () => {
      this.props.setTaskListGroupMode('GROUP_MODE_NONE')
      $groupModeBtn.popover('hide')
    })

    $groupModeBtn.popover({
      container: 'body',
      html: true,
      placement: 'left',
      content: document.querySelector('#task-list-group-mode-template').textContent
    })
  }

  renderGroup(group, tasks) {
    return (
      <TaskGroup key={ group.id } group={ group } tasks={ tasks } />
    )
  }

  render() {

    const { taskListGroupMode } = this.props
    let { unassignedTasks } = this.props
    const groupsMap = new Map()
    const groups = []
    let standaloneTasks = []

    if (taskListGroupMode === 'GROUP_MODE_FOLDERS') {

      const tasksWithGroup = _.filter(unassignedTasks, task => task.hasOwnProperty('group') && task.group)

      _.forEach(tasksWithGroup, task => {
        const keys = Array.from(groupsMap.keys())
        const group = _.find(keys, group => group.id === task.group.id)
        if (!group) {
          groupsMap.set(task.group, [ task ])
        } else {
          groupsMap.get(group).push(task)
        }
      })
      groupsMap.forEach((tasks, group) => {
        groups.push(this.renderGroup(group, tasks))
      })

      standaloneTasks = _.filter(unassignedTasks, task => !task.hasOwnProperty('group') || !task.group)

    } else {
      standaloneTasks = unassignedTasks
    }

    standaloneTasks.sort((a, b) => {
      return moment(a.doneBefore).isBefore(b.doneBefore) ? -1 : 1
    })

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
            <a href="#" id="task-list-group-mode" title={ this.props.t('ADMIN_DASHBOARD_DISPLAY') }>
              <i className="fa fa-list"></i>
            </a>
          </span>
        </h4>
        <div className="dashboard__panel__scroll">
          <div className="list-group nomargin">
            { groups }
            { _.map(standaloneTasks, (task, key) => {
              return (
                <Task key={ key } task={ task } />
              )
            })}
          </div>
        </div>
      </div>
    )
  }
}

function mapStateToProps (state) {
  return {
    unassignedTasks: selectFilteredTasks({
      tasks: state.unassignedTasks,
      filters: state.filters,
      date: state.date,
    }),
    taskListGroupMode: state.taskListGroupMode,
    showCancelledTasks: state.filters.showCancelledTasks,
    taskModalIsOpen: state.taskModalIsOpen
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
