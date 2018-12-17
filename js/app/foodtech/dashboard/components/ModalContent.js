import React from 'react'
import { connect } from 'react-redux'
import { translate } from 'react-i18next'
import Timeline from 'antd/lib/timeline'
import moment from 'moment'

import {
  setCurrentOrder,
  acceptOrder,
  refuseOrder,
  delayOrder,
  cancelOrder
} from '../redux/actions'

import OrderItems from './OrderItems'
import OrderTotal from './OrderTotal'

class ModalContent extends React.Component {

  cancelOrder() {
    const { order } = this.props

    this.props.cancelOrder(order)
  }

  delayOrder() {
    const { order } = this.props

    this.props.delayOrder(order)
  }

  refuseOrder() {
    const { order } = this.props

    this.props.refuseOrder(order)
  }

  acceptOrder() {
    const { order } = this.props

    this.props.acceptOrder(order)
  }

  renderTimeline() {
    const { order } = this.props

    return (
      <Timeline>
        <Timeline.Item dot={<i className="fa fa-cutlery"></i>}>
          <span>Préparation à { moment(order.preparationExpectedAt).format('LT') }</span>
        </Timeline.Item>
        <Timeline.Item dot={<i className="fa fa-cube"></i>}>
          <span>Pickup à { moment(order.pickupExpectedAt).format('LT') }</span>
          <br />
          <span>{ order.restaurant.address.streetAddress }</span>
        </Timeline.Item>
        <Timeline.Item dot={<i className="fa fa-arrow-down"></i>}>
          <span>Dropoff à { moment(order.shippedAt).format('LT') }</span>
          <br />
          <span>{ order.shippingAddress.streetAddress }</span>
        </Timeline.Item>
      </Timeline>
    )
  }

  renderButtons() {
    const { order } = this.props

    if (order.state === 'new') {
      return (
        <div>
          <hr />
          <div className="row">
            <div className="col-sm-4">
              <button onClick={ this.refuseOrder.bind(this) } className="btn btn-block btn-danger">
                <i className="fa fa-ban" aria-hidden="true"></i>  { this.props.t('ADMIN_DASHBOARD_ORDERS_REFUSE') }
              </button>
            </div>
            <div className="col-sm-8">
              <button onClick={ this.acceptOrder.bind(this) } className="btn btn-block btn-primary">
                <i className="fa fa-check" aria-hidden="true"></i>  { this.props.t('ADMIN_DASHBOARD_ORDERS_ACCEPT') }
              </button>
            </div>
          </div>
        </div>
      )
    }

    if (order.state === 'accepted') {
      return (
        <div>
          <hr />
          <div className="row">
            <div className="col-sm-4">
              <button onClick={ this.cancelOrder.bind(this) } className="btn btn-block btn-danger">
                <i className="fa fa-ban" aria-hidden="true"></i>  { this.props.t('ADMIN_DASHBOARD_ORDERS_CANCEL') }
              </button>
            </div>
            <div className="col-sm-8">
              <button onClick={ this.delayOrder.bind(this) } className="btn btn-block btn-primary">
                <i className="fa fa-clock-o" aria-hidden="true"></i>  { this.props.t('ADMIN_DASHBOARD_ORDERS_DELAY') }
              </button>
            </div>
          </div>
        </div>
      )
    }
  }

  renderNotes() {

    const { order } = this.props

    return (
      <div>
        <h5>
          <i className="fa fa-user"></i>  { this.props.t('ADMIN_DASHBOARD_ORDERS_NOTES') }
        </h5>
        <div className="speech-bubble">
          <i className="fa fa-quote-left"></i>  { order.notes }
        </div>
      </div>
    )
  }

  render() {

    const { order } = this.props

    return (
      <div className="panel panel-default">
        <div className="panel-heading">
          <span>Order { order.number } (#{ order.id })</span>
          <a className="pull-right" onClick={ () => this.props.setCurrentOrder(null) }>
            <i className="fa fa-close"></i>
          </a>
        </div>
        <div className="panel-body">
          <h5><i className="fa fa-cutlery"></i>  { order.restaurant.name }</h5>
          <h5>{ this.props.t('ADMIN_DASHBOARD_ORDERS_DISHES') }</h5>
          <OrderItems order={ order } />
          <OrderTotal order={ order } />
          { order.notes && this.renderNotes() }
          <h5>Timeline</h5>
          { this.renderTimeline() }
          { this.renderButtons() }
        </div>
      </div>
    )
  }
}

function mapStateToProps() {
  return {}
}

function mapDispatchToProps(dispatch) {
  return {
    setCurrentOrder: order => dispatch(setCurrentOrder(order)),
    acceptOrder: order => dispatch(acceptOrder(order)),
    refuseOrder: order => dispatch(refuseOrder(order)),
    delayOrder: order => dispatch(delayOrder(order)),
    cancelOrder: order => dispatch(cancelOrder(order)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(translate()(ModalContent))
