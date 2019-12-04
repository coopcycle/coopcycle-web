import React from 'react'
import { connect } from 'react-redux'
import moment from 'moment'
import Sortable from 'react-sortablejs'
import { withTranslation } from 'react-i18next'
import _ from 'lodash'

import Task from './Task'
import { removeTasks, modifyTaskList, togglePolyline } from '../redux/actions'
import { selectFilteredTasks } from '../redux/selectors'

moment.locale($('html').attr('lang'))

class TaskList extends React.Component {

  constructor (props) {
    super(props)
    this.state = {
      collapsed: props.collapsed
    }
  }

  componentDidMount() {

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
  }

  remove(task) {
    this.props.removeTasks(this.props.username, task)
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

    const taskListClasslist = ['taskList__tasks', 'list-group', 'nomargin']
    if (tasks.length === 0) {
      taskListClasslist.push('taskList__tasks--empty')
    }

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
          <Sortable
            className={ taskListClasslist.join(' ') }
            onChange={ (order, sortable, e) => {

              if (e.type === 'add' || e.type === 'update') {

                const isTask = e.item.hasAttribute('data-task-id')

                let tasks = []
                if (isTask) {

                  tasks = order.map(id => ({ '@id': id }))

                  if (e.type === 'add') {
                    const task = _.find(this.props.allTasks, t => t['@id'] === e.item.getAttribute('data-task-id'))

                    if (task.previous) {
                      // If previous task is another day, will be null
                      const previousTask = _.find(this.props.allTasks, t => t['@id'] === task.previous)
                      if (previousTask) {
                        Array.prototype.splice.apply(tasks,
                          Array.prototype.concat([ e.newIndex, 0 ], previousTask))
                      }
                    } else if (task.next) {
                      // If next task is another day, will be null
                      const nextTask = _.find(this.props.allTasks, t => t['@id'] === task.next)
                      if (nextTask) {
                        Array.prototype.splice.apply(tasks,
                          Array.prototype.concat([ e.newIndex + 1, 0 ], nextTask))
                      }
                    }
                  }

                } else {

                  const tasksFromGroup = Array
                    .from(e.item.querySelectorAll('[data-task-id]'))
                    .map(el => _.find(this.props.allTasks, t => t['@id'] === el.getAttribute('data-task-id')))

                  tasks = this.props.items.slice()

                  Array.prototype.splice.apply(tasks,
                    Array.prototype.concat([ e.newIndex, 0 ], tasksFromGroup))
                }

                this.props.modifyTaskList(this.props.username, tasks)
              }
            }}
            options={{
              dataIdAttr: 'data-task-id',
              group: {
                name: 'assigned',
                put: ['unassigned'],
              },
            }}>
            { tasks.map(task => (
              <Task
                key={ task['@id'] }
                task={ task }
                assigned={ true }
                onRemove={ task => this.remove(task) }
              />
            ))}
          </Sortable>
        </div>
      </div>
    )
  }
}

function mapStateToProps(state, ownProps) {
  return {
    polylineEnabled: state.polylineEnabled[ownProps.username],
    allTasks: state.allTasks,
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
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(TaskList))
