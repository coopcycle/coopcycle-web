import React from 'react'
import moment from 'moment'
import { withTranslation } from 'react-i18next'

moment.locale($('html').attr('lang'))

class NotificationList extends React.Component {

  render() {

    const { notifications, count, onSeeAll, onRemove } = this.props

    if (notifications.length === 0) {
      return (
        <div className="alert alert-warning nomargin">{ this.props.t('NOTIFICATIONS_EMPTY') }</div>
      )
    }

    return (
      <div>
        <ul className="nav nav-pills nav-stacked" style={ {height: '60vh', overflow: 'auto'} }>
          { notifications.map((notification, key) => (
            <li className='d-flex justify-content-between align-items-center' key={ `notification-${key}` }>
              <a className='flex-grow-1'>
                { notification.message }
                <br />
                <small>{ moment.unix(notification.timestamp).fromNow() }</small>
              </a>
              <a role="button" href="#" className="text-reset"
                onClick={ () => onRemove(notification) }>
                <i className="fa fa-trash"></i>
              </a>
            </li>
          ))}
        </ul>
        {
          count > notifications.length &&
          <button type="button" className="btn btn-block btn-primary mt-2" onClick={ onSeeAll }>
            { this.props.t('SEE_ALL') } ({count})
          </button>
        }
      </div>
    )
  }
}

export default withTranslation(['common'], { withRef: true })(NotificationList)
