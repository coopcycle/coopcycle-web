import React from 'react'
import Task from './Task'

export default class extends React.Component {
  render() {
    const { tasks } = this.props
    return (
      <div className="task-group" data-task-group="true">
        { tasks.map(task => {
          return (
            <Task key={ task['@id'] } eventEmitter={ this.props.eventEmitter } task={ task } assigned={ false } />
          )
        })}
      </div>
    )
  }
}
