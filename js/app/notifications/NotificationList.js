import React from 'react'
import moment from 'moment'
import { withTranslation } from 'react-i18next'

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

  render() {

    const { notifications } = this.state

    if (notifications.length === 0) {
      return (
        <div className="alert alert-warning nomargin">{ this.props.t('NOTIFICATIONS_EMPTY') }</div>
      )
    }

    return (
      <ul className="nav nav-pills nav-stacked">
        { notifications.map((notification, key) => (
          <li key={ `notification-${key}` }>
            <a>
              { notification.message }
              <br />
              <small>{ moment.unix(notification.timestamp).fromNow() }</small>
            </a>
          </li>
        ))}
      </ul>
    )
  }
}

export default withTranslation(['common'], { withRef: true })(NotificationList)
