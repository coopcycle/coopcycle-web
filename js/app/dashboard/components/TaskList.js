import React from 'react'
import { connect } from 'react-redux'
import moment from 'moment'
import dragula from 'react-dragula'
import { withTranslation } from 'react-i18next'
import _ from 'lodash'
import Task from './Task'
import { removeTasks, modifyTaskList, togglePolyline, drakeDrag, drakeDragEnd } from '../redux/actions'
import { selectFilteredTasks } from '../redux/selectors'

moment.locale($('html').attr('lang'))

class TaskList extends React.Component {

  constructor (props) {
    super(props)
    this.state = {
      collapsed: props.collapsed
    }
    this.taskListRef = React.createRef()
  }

  componentDidMount() {
    this.props.taskListDidMount(this)

    const { username, collapsed } = this.props

    $('#collapse-' + username).on('shown.bs.collapse', () => {
      this.setState({ collapsed: false })
    })

    $('#collapse-' + username).on('hidden.bs.collapse', () => {
      this.setState({ collapsed: true })
    })

    if (!collapsed) {
      $('#collapse-' + username).collapse('show')
    }

    // handler to change the task order within a courier tasklist
    dragula([ this.taskListRef.current ], {
      // You can set accepts to a method with the following signature: (el, target, source, sibling).
      // It'll be called to make sure that an element el, that came from container source,
      // can be dropped on container target before a sibling element.
      // The sibling can be null, which would mean that the element would be placed as the last element in the container.
      accepts: (el, target, source, sibling) => {

        if (el === sibling) {
          return true
        }

        const { tasks } = this.props

        const draggedTask = _.find(tasks, task => task['@id'] === el.getAttribute('data-task-id'))

        if (!draggedTask.previous && !draggedTask.next) {
          return true
        }

        const taskOrder = _.map(tasks, task => task['@id'])

        let siblingTaskIndex
        if (sibling === null) {
          siblingTaskIndex = tasks.length - 1
        } else {
          const siblingTask = _.find(tasks, task => task['@id'] === sibling.getAttribute('data-task-id'))
          siblingTaskIndex  = taskOrder.indexOf(siblingTask['@id'])
        }

        if (draggedTask.previous) {
          const previousTaskIndex = taskOrder.indexOf(draggedTask.previous)
          if (siblingTaskIndex <= previousTaskIndex) {
            return false
          }
        }

        if (draggedTask.next) {
          const nextTaskIndex = taskOrder.indexOf(draggedTask.next)
          if (siblingTaskIndex >= nextTaskIndex) {
            return false
          }
        }

        return true
      }
    }).on('drop', (element, target) => {

      const { tasks } = this.props

      const elements = target.querySelectorAll('.list-group-item')
      const tasksOrder = _.map(elements, element => element.getAttribute('data-task-id'))

      let newTasks = tasks.slice()
      newTasks.sort((a, b) => {
        const keyA = tasksOrder.indexOf(a['@id'])
        const keyB = tasksOrder.indexOf(b['@id'])

        return keyA > keyB ? 1 : -1
      })

      this.props.modifyTaskList(this.props.username, newTasks)

    }).on('drag', () => {
      this.props.drakeDrag()
    }).on('dragend', () => {
      this.props.drakeDragEnd()
    })

  }

  remove(taskToRemove) {

    // Check if we need to remove another linked task
    // FIXME
    // Make it work when more than 2 tasks are linked together
    let tasksToRemove = [ taskToRemove ]
    if (taskToRemove.previous || taskToRemove.next) {
      const linkedTasks = _.filter(this.props.tasks, task => task['@id'] === (taskToRemove.previous || taskToRemove.next))
      tasksToRemove = tasksToRemove.concat(linkedTasks)
    }

    this.props.removeTasks(this.props.username, tasksToRemove)
  }

  render() {

    const {
      duration,
      distance,
      username,
      polylineEnabled,
    } = this.props

    let { tasks } = this.props

    const { collapsed } = this.state

    tasks = _.orderBy(tasks, ['position', 'id'])

    const durationFormatted = moment.utc()
      .startOf('day')
      .add(duration, 'seconds')
      .format('HH:mm')

    const distanceFormatted = (distance / 1000).toFixed(2) + ' Km',
      collabsableId = ['collapse', username].join('-')

    const polylineClassNames = ['pull-right', 'taskList__summary-polyline']
    if (polylineEnabled) {
      polylineClassNames.push('taskList__summary-polyline--enabled')
    }

    const avatarURL = window.Routing.generate('user_avatar', { username })

    return (
      <div className="panel panel-default nomargin noradius noborder">
        <div className="panel-heading  dashboard__panel__heading">
          <h3
            className="panel-title"
            role="button"
            data-toggle="collapse"
            data-target={ '#' + collabsableId }
            aria-expanded={ collapsed ? 'false' : 'true' }
          >
            <img src={ avatarURL } width="20" height="20" /> 
            <a
              className="dashboard__panel__heading__link"
            >
              { username }
              &nbsp;&nbsp;
              <span className="badge">{ tasks.length }</span>
              &nbsp;&nbsp;
              <i className={ collapsed ? 'fa fa-caret-down' : 'fa fa-caret-up' }></i>
            </a>
          </h3>
        </div>
        <div role="tabpanel" id={ collabsableId } className="collapse">
          { tasks.length > 0 && (
            <div className="panel-body taskList__summary">
              <strong>{ this.props.t('ADMIN_DASHBOARD_DURATION') }</strong>  <span>{ durationFormatted }</span> - <strong>{ this.props.t('ADMIN_DASHBOARD_DISTANCE') }</strong>  <span>{ distanceFormatted }</span>
              <a role="button" className={ polylineClassNames.join(' ') } onClick={ () => this.props.togglePolyline(username) }>
                <i className="fa fa-map fa-2x"></i>
              </a>
            </div>
          )}
          <div className="list-group dropzone" data-username={ username }>
            <div className="list-group-item text-center dropzone-item">
              { this.props.t('ADMIN_DASHBOARD_DROP_DELIVERIES') }
            </div>
          </div>
          <div ref={ this.taskListRef } className="taskList__tasks list-group nomargin">
            { tasks.map(task => (
              <Task
                key={ task['@id'] }
                task={ task }
                assigned={ true }
                onRemove={ task => this.remove(task) }
              />
            ))}
          </div>
        </div>
      </div>
    )
  }
}

function mapStateToProps(state, ownProps) {
  return {
    polylineEnabled: state.polylineEnabled[ownProps.username],
    tasks: selectFilteredTasks({
      tasks: ownProps.items,
      filters: state.filters,
      date: state.date,
    }),
    distance: ownProps.distance,
    duration: ownProps.duration,
    filters: state.filters
  }
}

function mapDispatchToProps(dispatch) {
  return {
    removeTasks: (username, tasks) => { dispatch(removeTasks(username, tasks)) },
    modifyTaskList: (username, tasks) => { dispatch(modifyTaskList(username, tasks)) },
    togglePolyline: (username) => { dispatch(togglePolyline(username)) },
    drakeDrag: () => dispatch(drakeDrag()),
    drakeDragEnd: () => dispatch(drakeDragEnd()),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(TaskList))
