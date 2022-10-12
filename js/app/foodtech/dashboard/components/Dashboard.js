import React from 'react'
import { connect } from 'react-redux'
import { withTranslation } from 'react-i18next'
import Modal from 'react-modal'
import { DatePicker, Slider, Col, Row, Switch } from 'antd'
import moment from 'moment'

import Column from './Column'
import ModalContent from './ModalContent'
import Tab from './Tab'
import Search from './Search'
import SlotViz from './SlotViz'
import { setCurrentOrder, orderCreated, setPreparationDelay, changeStatus, setActiveTab } from '../redux/actions'
import { selectNewOrders, selectAcceptedOrders, selectFulfilledOrders, selectCancelledOrders } from '../redux/selectors'

class Dashboard extends React.Component {

  constructor(props) {
    super(props)
    this.sliderMarks = {
      0: props.t('RESTAURANT_DASHBOARD_DELAY_MARK_NONE'),
      15: '15min',
      30: '30min',
      45: '45min',
      60: '1h',
      75: '1h15',
      90: '1h30',
      120: '2h',
      150: '2h30',
      180: '3h',
    }
  }

  componentDidMount() {
    $(function () {
      $('[data-toggle="popover"]').tooltip()
    })

    if (this.props.initialOrder) {
      this.props.setCurrentOrder({ '@id': this.props.initialOrder })
    }
  }

  afterOpenModal() {
  }

  closeModal() {
    this.props.setCurrentOrder(null)
  }

  _tipFormatter(value) {
    return this.sliderMarks[value]
  }

  render() {

    return (
      <div className="FoodtechDashboard">
        <div className="FoodtechDashboard__Navbar">
          { this.props.showSettings && (
            <div className="FoodtechDashboard__Navbar__Slider">
              <Row type="flex" align="middle">
                <Col span={ 6 }>
                  <span>
                    <i className="fa fa-clock-o"></i> { this.props.t('RESTAURANT_DASHBOARD_DELAY_SETTING') }
                  </span>
                </Col>
                <Col span={ 18 }>
                  <Slider
                    max={ 180 }
                    defaultValue={ this.props.preparationDelay }
                    marks={ this.sliderMarks }
                    step={ null }
                    tipFormatter={ this._tipFormatter.bind(this) }
                    onChange={ delay => this.props.setPreparationDelay(delay) } />
                </Col>
              </Row>
            </div>
          )}
          { this.props.showSearch && (
            <div className="FoodtechDashboard__Navbar__Search">
              <Search />
            </div>
          )}
          { this.props.restaurant && (
            <div>
              <Switch
                unCheckedChildren={ this.props.t('ADMIN_DASHBOARD_NORMAL') }
                checkedChildren={ this.props.t('ADMIN_DASHBOARD_RUSH') }
                onChange={ checked => {
                  this.props.changeStatus(this.props.restaurant, checked ? 'rush' : 'normal')
                }}
                defaultChecked={ this.props.isRushEnabled }
              />
              <div className="glyphicon glyphicon-question-sign rushInfoSize"
                data-toggle="popover"
                data-placement="right"
                title={ this.props.t('RESTAURANT_DASHBOARD_INFO_RUSH') }>
              </div>
            </div>
          )}
          {
            this.props.restaurant && (
            <div>
              <a href={window.Routing.generate('dashboard_restaurant_new_adhoc_order', {restaurantId: this.props.restaurant.id})} className="btn btn-success">
                { this.props.t('CREATE_ORDER') }
              </a>
            </div>
            )
          }
          <div>
            <DatePicker
              format={ 'll' }
              defaultValue={ moment(this.props.date) }
              onChange={ (date) => {
                if (date) {
                  this.props.onDateChange(date)
                }
              }} />
          </div>
        </div>
        <div className="FoodtechDashboard__SlotViz">
          <SlotViz />
        </div>
        <div className="FoodtechDashboard__Columns">
          <Column
            orders={ this.props.newOrders }
            title={ this.props.t('RESTAURANT_DASHBOARD_NEW_ORDERS') }
            context="warning"
            active={ this.props.activeTab == 'new' }
            onCardClick={ order => this.props.setCurrentOrder(order) } />
          <Column
            orders={ this.props.acceptedOrders }
            title={ this.props.t('RESTAURANT_DASHBOARD_ACCEPTED_ORDERS') }
            context="info"
            active={ this.props.activeTab == 'accepted' }
            onCardClick={ order => this.props.setCurrentOrder(order) } />
          <Column
            orders={ this.props.fulfilledOrders }
            title={ this.props.t('RESTAURANT_DASHBOARD_FULFILLED_ORDERS') }
            context="success"
            active={ this.props.activeTab == 'fulfilled' }
            onCardClick={ order => this.props.setCurrentOrder(order) } />
          <Column
            orders={ this.props.cancelledOrders }
            title={ this.props.t('RESTAURANT_DASHBOARD_CANCELLED_REFUSED_ORDERS') }
            context="danger"
            active={ this.props.activeTab == 'cancelled' }
            onCardClick={ order => this.props.setCurrentOrder(order) } />
        </div>
        <nav className="FoodtechDashboard__TabNav">
          <Tab
            title={ `${this.props.t('new')} (${this.props.newOrders.length})` }
            target="new"
            onClick={ (tab) => this.props.setActiveTab(tab) }
            active={ this.props.activeTab === 'new' } />
          <Tab
            title={ `${this.props.t('accepted')} (${this.props.acceptedOrders.length})` }
            target="accepted"
            onClick={ (tab) => this.props.setActiveTab(tab) }
            active={ this.props.activeTab === 'accepted' } />
          <Tab
            title={ `${this.props.t('fulfilled')} (${this.props.fulfilledOrders.length})` }
            target="fulfilled"
            onClick={ (tab) => this.props.setActiveTab(tab) }
            active={ this.props.activeTab === 'fulfilled' } />
          <Tab
            title={ `${this.props.t('cancelled')} (${this.props.cancelledOrders.length})` }
            target="cancelled"
            onClick={ (tab) => this.props.setActiveTab(tab) }
            active={ this.props.activeTab === 'cancelled' } />
        </nav>
        <Modal
          isOpen={ this.props.modalIsOpen }
          onAfterOpen={ this.afterOpenModal.bind(this) }
          onRequestClose={ this.closeModal.bind(this) }
          shouldCloseOnOverlayClick={ true }
          contentLabel={ this.props.order ?
            this.props.t('RESTAURANT_DASHBOARD_ORDER_TITLE', { number: this.props.order.number, id: this.props.order.id }) : '' }
          overlayClassName="ReactModal__Overlay--foodtech"
          className="ReactModal__Content--foodtech">
          { this.props.order && <ModalContent order={ this.props.order } /> }
        </Modal>
      </div>
    )
  }
}

function mapStateToProps(state) {

  const isRushEnabled = state.restaurant && state.restaurant.state === 'rush'

  return {
    date: state.date,
    order: state.order,
    modalIsOpen: state.order !== null,
    newOrders: selectNewOrders(state),
    acceptedOrders: selectAcceptedOrders(state),
    fulfilledOrders: selectFulfilledOrders(state),
    cancelledOrders: selectCancelledOrders(state),
    preparationDelay: state.preparationDelay,
    showSettings: state.showSettings,
    showSearch: state.showSearch,
    isRushEnabled: isRushEnabled,
    restaurant: state.restaurant,
    activeTab: state.activeTab,
    initialOrder: state.initialOrder,
  }
}

function mapDispatchToProps(dispatch) {

  return {
    setCurrentOrder: order => dispatch(setCurrentOrder(order)),
    setPreparationDelay: delay => dispatch(setPreparationDelay(delay)),
    orderCreated: order => dispatch(orderCreated(order)),
    changeStatus: (restaurant, state) => dispatch(changeStatus(restaurant, state)),
    setActiveTab: tab => dispatch(setActiveTab(tab)),
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(withTranslation()(Dashboard))
