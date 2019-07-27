import React from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import _ from 'lodash'
import Modal from 'react-modal'
import DatePicker from 'antd/lib/date-picker'
import Slider from 'antd/lib/slider'
import { Row, Col, } from 'antd/lib/grid'
import moment from 'moment'

import Column from './Column'
import ModalContent from './ModalContent'

import { setCurrentOrder, orderCreated, setPreparationDelay } from '../redux/actions'

function sortByShippedAt(a, b) {
  if (moment(a.shippedAt).isSame(moment(b.shippedAt))) {
    return 0
  }

  return moment(a.shippedAt).isBefore(moment(b.shippedAt)) ? -1 : 1
}

class Dashboard extends React.Component {

  afterOpenModal() {
  }

  closeModal() {
    this.props.setCurrentOrder(null)
  }

  _tipFormatter(value) {
    if (value === 0) {

      return this.props.t('RESTAURANT_DASHBOARD_TIP_NO_DELAY')
    }

    return this.props.t('RESTAURANT_DASHBOARD_TIP_APPLY_DELAY', { delay: value })
  }

  render() {

    const sliderMarks = {
      0: this.props.t('RESTAURANT_DASHBOARD_DELAY_MARK_NONE'),
      15: '15 min',
      30: '30 min',
    }

    return (
      <div className="FoodtechDashboard">
        <div className="FoodtechDashboard__Navbar">
          <div>
            { this.props.showSettings && (
              <Row type="flex" align="middle">
                <Col span={ 6 }>
                  <span>
                    <i className="fa fa-clock-o"></i> { this.props.t('RESTAURANT_DASHBOARD_DELAY_SETTING') }
                  </span>
                </Col>
                <Col span={ 18 }>
                  <Slider
                    max={ 30 }
                    defaultValue={ this.props.preparationDelay }
                    marks={ sliderMarks }
                    step={ null }
                    tipFormatter={ this._tipFormatter.bind(this) }
                    onChange={ delay => this.props.setPreparationDelay(delay) } />
                </Col>
              </Row>
            )}
          </div>
          <div>
            <DatePicker
              format={ 'll' }
              defaultValue={ moment(this.props.date) }
              onChange={ (date) => this.props.onDateChange(date) } />
          </div>
        </div>
        <div className="FoodtechDashboard__Columns">
          <Column orders={ this.props.newOrders } title={ this.props.t('RESTAURANT_DASHBOARD_NEW_ORDERS') } />
          <Column orders={ this.props.acceptedOrders } title={ this.props.t('RESTAURANT_DASHBOARD_ACCEPTED_ORDERS') } />
          <Column orders={ this.props.fulfilledOrders } title={ this.props.t('RESTAURANT_DASHBOARD_FULFILLED_ORDERS') } />
          <Column orders={ this.props.cancelledOrders } title={ this.props.t('RESTAURANT_DASHBOARD_CANCELLED_REFUSED_ORDERS') } />
        </div>
        <Modal
          isOpen={ this.props.modalIsOpen }
          onAfterOpen={ this.afterOpenModal.bind(this) }
          onRequestClose={ this.closeModal.bind(this) }
          shouldCloseOnOverlayClick={ true }
          contentLabel={ 'PLOP' }
          className="ReactModal__Content--foodtech">
          { this.props.order && <ModalContent order={ this.props.order } /> }
        </Modal>
      </div>
    )
  }

}

function mapStateToProps(state) {

  // Make sure orders are for the current date
  // TODO This should be managed at reducer level
  const orders =
    _.filter(state.orders, order => moment(order.shippedAt).format('YYYY-MM-DD') === state.date)

  const newOrders =
    _.filter(orders, order => order.state === 'new')
  const acceptedOrders =
    _.filter(orders, order => order.state === 'accepted')
  const fulfilledOrders =
    _.filter(orders, order => order.state === 'fulfilled')
  const cancelledOrders =
    _.filter(orders, order => order.state === 'refused' || order.state === 'cancelled')

  return {
    date: state.date,
    order: state.order,
    modalIsOpen: state.order !== null,
    newOrders: newOrders.sort(sortByShippedAt),
    acceptedOrders: acceptedOrders.sort(sortByShippedAt),
    fulfilledOrders: fulfilledOrders.sort(sortByShippedAt),
    cancelledOrders: cancelledOrders.sort(sortByShippedAt),
    preparationDelay: state.preparationDelay,
    showSettings: state.showSettings,
  }
}

function mapDispatchToProps(dispatch) {
  return {
    setCurrentOrder: order => dispatch(setCurrentOrder(order)),
    setPreparationDelay: delay => dispatch(setPreparationDelay(delay)),
    orderCreated: order => dispatch(orderCreated(order)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(Dashboard))
