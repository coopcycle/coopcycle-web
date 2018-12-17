import React from 'react'
import { connect } from 'react-redux'
import { translate } from 'react-i18next'
import _ from 'lodash'
import Modal from 'react-modal'
import DatePicker from 'antd/lib/date-picker'
import moment from 'moment'

import Column from './Column'
import ModalContent from './ModalContent'

import { setCurrentOrder, orderCreated } from '../redux/actions'

Modal.setAppElement(document.getElementById('foodtech-dashboard'))

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

  render() {
    return (
      <div className="FoodtechDashboard">
        <div className="FoodtechDashboard__Navbar">
          <DatePicker
            format={ 'll' }
            defaultValue={ moment(this.props.date) }
            onChange={(date) => {
              window.location.href =
                window.Routing.generate('admin_foodtech_dashboard', { date: date.format('YYYY-MM-DD') })
            }} />
        </div>
        <div className="FoodtechDashboard__Columns">
          <Column orders={ this.props.newOrders } title={ 'New orders' } />
          <Column orders={ this.props.acceptedOrders } title={ 'Accepted orders' } />
          <Column orders={ this.props.fulfilledOrders } title={ 'Fulfilled orders' } />
          <Column orders={ this.props.cancelledOrders } title={ 'Refused/cancelled orders' } />
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
  }
}

function mapDispatchToProps(dispatch) {
  return {
    setCurrentOrder: order => dispatch(setCurrentOrder(order)),
    orderCreated: order => dispatch(orderCreated(order)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(translate()(Dashboard))
