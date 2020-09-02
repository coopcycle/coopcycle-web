import React, { useState } from 'react'
import { render } from 'react-dom'
import _ from 'lodash'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import moment from 'moment'
import { ReactSortable, Sortable, MultiDrag } from 'react-sortablejs'
import Task from './Task'
import TaskGroup from './TaskGroup'
import UnassignedTasksPopoverContent from './UnassignedTasksPopoverContent'
import { setTaskListGroupMode, openNewTaskModal, closeNewTaskModal, toggleSearch } from '../redux/actions'

const UnassignedTasksPopoverContentWithTrans = withTranslation()(UnassignedTasksPopoverContent)

Sortable.mount(new MultiDrag())

// @see https://github.com/SortableJS/react-sortablejs/issues/145#issuecomment-619060227
const SortableList = ({ tasks }) => {

  const [ state, setState ] = useState(tasks)

  return (
    <ReactSortable
      list={ state }
      setList={ setState }
      className="list-group nomargin"
      onChange={ (/*order, sortable, e*/) => {
        // console.log('UnassignedTasks.Sortable.onChange', order, e)
      }}
      multiDrag
      sort= { false }
      dataIdAttr="data-task-id"
      group={{ name: 'unassigned' }}>
      { state.map((task) => {
        return (
          <Task key={ task['@id'] } task={ task } />
        )
      })}
    </ReactSortable>
  )
}

class UnassignedTasks extends React.Component {

  toggleDisplay(e) {
    e.preventDefault()

    const $target = $(e.currentTarget)

    if (!$target.data('bs.popover')) {

      const el = document.createElement('div')

      const cb = () => {
        $target.popover({
          trigger: 'manual',
          html: true,
          container: 'body',
          placement: 'left',
          content: el,
          template: '<div class="popover" role="tooltip"><div class="arrow"></div><div class="popover-content"></div></div>'
        })
        $target.popover('toggle')
      }

      render(<UnassignedTasksPopoverContentWithTrans
        defaultValue={ this.props.taskListGroupMode }
        onChange={ mode => {
          this.props.setTaskListGroupMode(mode)
          $target.popover('hide')
        }} />, el, cb)

    } else {
      $target.popover('toggle')
    }
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
    let standaloneTasks = unassignedTasks

    if (taskListGroupMode === 'GROUP_MODE_FOLDERS') {

      const tasksWithGroup = _.filter(unassignedTasks, task => Object.prototype.hasOwnProperty.call(task, 'group') && task.group)

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

      standaloneTasks = _.filter(unassignedTasks, task => !Object.prototype.hasOwnProperty.call(task, 'group') || !task.group)
    }

    // Order by dropoff desc, with pickup before
    if (taskListGroupMode === 'GROUP_MODE_DROPOFF_DESC') {

      const dropoffTasks = _.filter(standaloneTasks, t => t.type === 'DROPOFF')
      dropoffTasks.sort((a, b) => {
        return moment(a.doneBefore).isBefore(b.doneBefore) ? -1 : 1
      })
      const grouped = _.reduce(dropoffTasks, (acc, task) => {
        if (task.previous) {
          const prev = _.find(standaloneTasks, t => t['@id'] === task.previous)
          if (prev) {
            acc.push(prev)
          }
        }
        acc.push(task)

        return acc
      }, [])

      standaloneTasks = grouped
    } else {
      standaloneTasks.sort((a, b) => {
        return moment(a.doneBefore).isBefore(b.doneBefore) ? -1 : 1
      })
    }

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
            <a href="#" onClick={ e => this.toggleDisplay(e) } title={ this.props.t('ADMIN_DASHBOARD_DISPLAY') }>
              <i className="fa fa-list"></i>
            </a>
          </span>
        </h4>
        <div className="dashboard__panel__scroll">
          <SortableList tasks={ standaloneTasks } />
        </div>
      </div>
    )
  }
}

function mapStateToProps (state) {

  return {
    unassignedTasks: state.unassignedTasks,
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
