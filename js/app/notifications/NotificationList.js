import React from 'react'
import moment from 'moment'

moment.locale($('html').attr('lang'))

class NotificationList extends React.Component {

  constructor(props) {
    super(props)

    this.state = {
      notifications: props.notifications.slice(0, 5)
    }
  }

  unshift(notification) {
    const { notifications } = this.state
    notifications.unshift(notification)
    this.setState({ notifications: notifications.slice(0, 5) })
  }

  toArray() {
    const { notifications } = this.state
    return notifications
  }

  componentDidUpdate() {
    this.props.onUpdate()
  }

  render() {

    const { notifications } = this.state

    if (notifications.length === 0) {
      return (
        <div className="alert alert-warning nomargin">{ this.props.emptyMessage }</div>
      )
    }

    return (
      <ul className="nav nav-pills nav-stacked">
        { notifications.map(notification => (
          <li key={ notification.id }>
            <a href={ notification.url ? notification.url : '#' }>
              { notification.message }
              <br />
              <small>{ moment(notification.createdAt).fromNow() }</small>
            </a>
          </li>
        ))}
      </ul>
    )
  }
}

export default NotificationList
