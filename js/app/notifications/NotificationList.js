import React from 'react'
import moment from 'moment'
import { withTranslation } from 'react-i18next'

moment.locale($('html').attr('lang'))

class NotificationList extends React.Component {

  constructor() {
    super()

    this.state = {
      loading: false,
    }

    this.onDeleteAll = this.onDeleteAll.bind(this)
  }

  onDeleteAll() {
    this.setState({loading: true})
    this.props.onDeleteAll()
      .then(() => {
        this.setState({loading: false})
      })
  }

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
              <a disabled={ this.state.loading } role="button" href="#" className="text-reset"
                onClick={ () => onRemove(notification) }>
                <i className="fa fa-close"></i>
              </a>
            </li>
          ))}
        </ul>
        <div className="d-flex mt-2">
          <button disabled={ this.state.loading } type="button" className="btn btn-block btn-primary mt-0 mr-2" onClick={ onSeeAll }>
            { this.props.t('SEE_ALL') } ({count})
          </button>
          <button disabled={ this.state.loading } type="button" className="btn btn-block btn-danger mt-0" onClick={ this.onDeleteAll }>
            { this.state.loading && <span><i className="fa fa-spinner fa-spin mr-2"></i></span> }
            { this.props.t('DELETE_ALL') } ({count})
          </button>
        </div>
      </div>
    )
  }
}

export default withTranslation(['common'], { withRef: true })(NotificationList)
