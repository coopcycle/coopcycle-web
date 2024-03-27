import React, { useEffect } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import { useTranslation } from 'react-i18next'
import Modal from 'react-modal'
import { DatePicker, Slider, Col, Row, Switch } from 'antd'
import moment from 'moment'

import Column from './Column'
import ModalContent from './ModalContent'
import Tab from './Tab'
import Search from './Search'
import SlotViz from './SlotViz'
import {
  setCurrentOrder,
  setPreparationDelay,
  changeStatus,
  setActiveTab,
} from '../redux/actions'
import {
  selectNewOrders,
  selectAcceptedOrders,
  selectFulfilledOrders,
  selectCancelledOrders,
} from '../redux/selectors'

export default function Dashboard({ onDateChange }) {
  const restaurant = useSelector(state => state.restaurant)
  const date = useSelector(state => state.date)
  const newOrders = useSelector(selectNewOrders)
  const acceptedOrders = useSelector(selectAcceptedOrders)
  const fulfilledOrders = useSelector(selectFulfilledOrders)
  const cancelledOrders = useSelector(selectCancelledOrders)
  const order = useSelector(state => state.order)
  const preparationDelay = useSelector(state => state.preparationDelay)
  const showSettings = useSelector(state => state.showSettings)
  const showSearch = useSelector(state => state.showSearch)
  const initialOrder = useSelector(state => state.initialOrder)
  const adhocOrderEnabled = useSelector(state => state.adhocOrderEnabled)

  const activeTab = useSelector(state => state.activeTab)
  const modalIsOpen = useSelector(state => state.order !== null)

  const isRushEnabled = restaurant && restaurant.state === 'rush'

  const { t } = useTranslation()

  const sliderMarks = {
    0: t('RESTAURANT_DASHBOARD_DELAY_MARK_NONE'),
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

  const dispatch = useDispatch()

  useEffect(() => {
    $(function () {
      $('[data-toggle="popover"]').tooltip()
    })

    if (initialOrder) {
      dispatch(setCurrentOrder({ '@id': initialOrder }))
    }
  }, [])

  const afterOpenModal = () => {
  }

  const closeModal = () => {
    dispatch(setCurrentOrder(null))
  }

  const _tipFormatter = (value) => {
    return sliderMarks[value]
  }

  return (
    <div className="FoodtechDashboard">
      <div className="FoodtechDashboard__Navbar">
        { showSettings && (
          <div className="FoodtechDashboard__Navbar__Slider">
            <Row type="flex" align="middle">
              <Col span={ 6 }>
                  <span>
                    <i className="fa fa-clock-o"></i> { t(
                    'RESTAURANT_DASHBOARD_DELAY_SETTING') }
                  </span>
              </Col>
              <Col span={ 18 }>
                <Slider
                  max={ 180 }
                  defaultValue={ preparationDelay }
                  marks={ sliderMarks }
                  step={ null }
                  tipFormatter={ _tipFormatter }
                  onChange={ delay => dispatch(setPreparationDelay(delay)) } />
              </Col>
            </Row>
          </div>
        ) }
        { showSearch && (
          <div className="FoodtechDashboard__Navbar__Search">
            <Search />
          </div>
        ) }
        { restaurant && (
          <div>
            <Switch
              unCheckedChildren={ t('ADMIN_DASHBOARD_NORMAL') }
              checkedChildren={ t('ADMIN_DASHBOARD_RUSH') }
              onChange={ checked => {
                dispatch(changeStatus(restaurant, checked ? 'rush' : 'normal'))
              } }
              defaultChecked={ isRushEnabled }
            />
            <div className="glyphicon glyphicon-question-sign rushInfoSize"
                 data-toggle="popover"
                 data-placement="right"
                 title={ t('RESTAURANT_DASHBOARD_INFO_RUSH') }>
            </div>
          </div>
        ) }
        {
          adhocOrderEnabled && restaurant && (
            <div>
              <a href={ window.Routing.generate(
                'dashboard_restaurant_new_adhoc_order',
                { restaurantId: restaurant.id }) }
                 className="btn btn-sm btn-success">
                { t('CREATE_ORDER') }
              </a>
            </div>
          )
        }
        <div>
          <DatePicker
            format={ 'll' }
            defaultValue={ moment(date) }
            onChange={ (date) => {
              if (date) {
                onDateChange(date)
              }
            } } />
        </div>
      </div>
      <div className="FoodtechDashboard__SlotViz">
        <SlotViz />
      </div>
      <div className="FoodtechDashboard__Columns">
        <Column
          orders={ newOrders }
          title={ t('RESTAURANT_DASHBOARD_NEW_ORDERS') }
          context="warning"
          active={ activeTab === 'new' }
          onCardClick={ order => dispatch(setCurrentOrder(order)) } />
        <Column
          orders={ acceptedOrders }
          title={ t('RESTAURANT_DASHBOARD_ACCEPTED_ORDERS') }
          context="info"
          active={ activeTab === 'accepted' }
          onCardClick={ order => dispatch(setCurrentOrder(order)) } />
        <Column
          orders={ fulfilledOrders }
          title={ t('RESTAURANT_DASHBOARD_FULFILLED_ORDERS') }
          context="success"
          active={ activeTab === 'fulfilled' }
          onCardClick={ order => dispatch(setCurrentOrder(order)) } />
        <Column
          orders={ cancelledOrders }
          title={ t('RESTAURANT_DASHBOARD_CANCELLED_REFUSED_ORDERS') }
          context="danger"
          active={ activeTab === 'cancelled' }
          onCardClick={ order => dispatch(setCurrentOrder(order)) } />
      </div>
      <nav className="FoodtechDashboard__TabNav">
        <Tab
          title={ `${ t('new') } (${ newOrders.length })` }
          target="new"
          onClick={ (tab) => dispatch(setActiveTab(tab)) }
          active={ activeTab === 'new' } />
        <Tab
          title={ `${ t('accepted') } (${ acceptedOrders.length })` }
          target="accepted"
          onClick={ (tab) => dispatch(setActiveTab(tab)) }
          active={ activeTab === 'accepted' } />
        <Tab
          title={ `${ t('fulfilled') } (${ fulfilledOrders.length })` }
          target="fulfilled"
          onClick={ (tab) => dispatch(setActiveTab(tab)) }
          active={ activeTab === 'fulfilled' } />
        <Tab
          title={ `${ t('cancelled') } (${ cancelledOrders.length })` }
          target="cancelled"
          onClick={ (tab) => dispatch(setActiveTab(tab)) }
          active={ activeTab === 'cancelled' } />
      </nav>
      <Modal
        isOpen={ modalIsOpen }
        onAfterOpen={ afterOpenModal }
        onRequestClose={ closeModal }
        shouldCloseOnOverlayClick={ true }
        contentLabel={ order ?
          t('RESTAURANT_DASHBOARD_ORDER_TITLE',
            { number: order.number, id: order.id }) : '' }
        overlayClassName="ReactModal__Overlay--foodtech"
        className="ReactModal__Content--foodtech">
        { order && <ModalContent order={ order } /> }
      </Modal>
    </div>
  )
}
