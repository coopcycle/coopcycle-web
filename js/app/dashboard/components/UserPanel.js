import React from 'react'
import { findDOMNode } from 'react-dom'
import Dragula from 'react-dragula';
import moment from 'moment'
import dragula from 'react-dragula'
import _ from 'lodash'
import Task from './Task'

moment.locale($('html').attr('lang'))

export default class extends React.Component {

  constructor(props) {
    super(props)
    this.state = {
      tasks: props.tasks || [],
      duration: props.duration || 0,
      distance: props.distance || 0,
      loading: false,
      collapsed: props.collapsed,
    }
  }

  componentDidMount() {

    this.props.onLoad(this, findDOMNode(this))

    const { username, collapsed } = this.props

    $('#collapse-' + username).on('shown.bs.collapse', () => {
      this.setState({ collapsed: false })
      this.props.onShow()
    })

    $('#collapse-' + username).on('hidden.bs.collapse', () => {
      this.setState({ collapsed: true })
      this.props.onHide()
    })

    if (!collapsed) {
      $('#collapse-' + username).collapse('show')
    }

    const container = findDOMNode(this).querySelector('.task-list')
    dragula([container], {

    }).on('drop', (element, target, source) => {

      const { tasks } = this.state

      const elements = target.querySelectorAll('.list-group-item')
      const tasksOrder = _.map(elements, element => element.getAttribute('data-task-id'))

      let newTasks = tasks.slice()
      newTasks.sort((a, b) => {
        const keyA = tasksOrder.indexOf(a['@id'])
        const keyB = tasksOrder.indexOf(b['@id'])

        return keyA > keyB ? 1 : -1
      })

      this.save(username, newTasks)

    })

  }

  save(username, tasks) {

    this.setState({ loading: true })

    return this.props.save(username, tasks).then(taskList => {
      this.setState({
        loading: false,
        tasks,
        duration: taskList.duration,
        distance: taskList.distance
      })
      this.props.onTaskListChange(username, taskList)
    })

  }

  add(task) {

    const { username } = this.props
    let { tasks } = this.state

    tasks = tasks.slice()

    if (Array.isArray(task)) {
      task.forEach(task => tasks.push(task))
    } else {
      tasks.push(task)
    }

    this.save(username, tasks)

  }

  remove(taskToRemove) {

    const { username } = this.props
    const { tasks } = this.state

    // Check if we need to remove another linked task
    let tasksToRemove = []
    if (taskToRemove.delivery) {
      tasksToRemove = _.filter(tasks, task => task.delivery['@id'] === taskToRemove.delivery['@id'])
    } else {
      tasksToRemove = [ taskToRemove ]
    }

    let newTasks = tasks.slice()

    _.remove(newTasks, task => _.find(tasksToRemove, taskToRemove => task['@id'] === taskToRemove['@id']))

    this.save(username, newTasks).then(() => {
      tasksToRemove.forEach(task => this.props.onRemove(task))
    })

  }

  render() {

    const { username, map } = this.props
    const { duration, distance, loading, collapsed } = this.state

    let { tasks } = this.state
    tasks.sort((a, b) => {
      return a.position > b.position ? 1 : -1
    })

    const durationFormatted = moment.utc()
      .startOf('day')
      .add(duration, 'seconds')
      .format('HH:mm')

    const distanceFormatted = (distance / 1000).toFixed(2) + ' Km'

    return (
      <div className="panel panel-default" style={{ opacity: loading ? 0.7 : 1 }}>
        <div className="panel-heading">
          <h3 className="panel-title">
            <i className="fa fa-user"></i> 
            <a role="button" data-toggle="collapse" data-parent="#accordion" href={ '#collapse-' + username }>{ username }</a> 
            { collapsed && ( <i className="fa fa-caret-down"></i> ) }
            { !collapsed && ( <i className="fa fa-caret-up"></i> ) }
            { loading && (
              <span className="pull-right"><i className="fa fa-spinner"></i></span>
            )}
          </h3>
        </div>
        <div id={ 'collapse-' + username } className="panel-collapse collapse" role="tabpanel">
          { tasks.length > 0 && (
            <div className="panel-body">
              <strong>Durée</strong>  <span>{ durationFormatted }</span>
              <br />
              <strong>Distance</strong>  <span>{ distanceFormatted }</span>
            </div>
          )}
          <div className="list-group dropzone">
            <div className="list-group-item text-center dropzone-item">
              Déposez les livraisons ici
            </div>
          </div>
          <div className="list-group task-list">
            { tasks.map(task => (
              <Task
                key={ task['@id'] }
                task={ task }
                assigned={ true }
                onRemove={ task => this.remove(task) } />
            ))}
          </div>
        </div>
      </div>
    )
  }
}
