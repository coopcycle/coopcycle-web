import React from 'react'
import { Timeline } from 'antd'
import moment from 'moment'

export default class extends React.Component {
  render() {

    const itemColor = event => {
      if (event.name === 'task:done') {
        return 'green'
      }
      if (event.name === 'task:failed') {
        return 'red'
      }

      return 'blue'
    }

    return (
      <Timeline>
        { this.props.task.events.map(event => (
          <Timeline.Item key={ event.createdAt + '-' + event.name } color={ itemColor(event) }>
            <p>{ moment(event.createdAt).format('LT') } { event.name }</p>
            { event.notes && (
              <p>{ event.notes }</p>
            ) }
          </Timeline.Item>
        )) }
      </Timeline>
    )
  }
}
