import React, { useEffect } from 'react'
import { useDispatch, useSelector } from 'react-redux'
import { useTranslation } from 'react-i18next'
import Modal from 'react-modal'
import { DatePicker, Slider, Col, Row, Switch, Tooltip } from 'antd'
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
  selectStartedOrders,
  selectReadyOrders,
} from '../redux/selectors'
import { AntdConfigProvider } from '../../../utils/antd'

export default function Dashboard({ onDateChange }) {
  const restaurant = useSelector(state => state.restaurant)
  const date = useSelector(state => state.date)
  const newOrders = useSelector(selectNewOrders)
  const acceptedOrders = useSelector(selectAcceptedOrders)
  const startedOrders = useSelector(selectStartedOrders)
  const readyOrders = useSelector(selectReadyOrders)
  const fulfilledOrders = useSelector(selectFulfilledOrders)
  const cancelledOrders = useSelector(selectCancelledOrders)
  const order = useSelector(state => state.order)
  const preparationDelay = useSelector(state => state.preparationDelay)
  const showSettings = useSelector(state => state.showSettings)
  const showSearch = useSelector(state => state.showSearch)
  const initialOrder = useSelector(state => state.initialOrder)

  const activeTab = useSelector(state => state.activeTab)
  const modalIsOpen = useSelector(state => state.order !== null)

  const isRushEnabled = restaurant && restaurant.state === 'rush'

  const { t } = useTranslation()

  const sliderMarks = {
    0: t('RESTAURANT_DASHBOARD_DELAY_MARK_NONE'),
    5: '5min',
    10: '10',
    15: '15',
    20: '20',
    25: '25',
    30: '30',
    35: '35',
    40: '40',
    45: '45',
    50: '50',
    55: '55',
    60: '1h',
    70: '1h10',
    90: '1h30',
    120: '2h'
  }

  const sections = [
    { key: 'new', title: t('new'), orders: newOrders, context: 'warning' },
    { key: 'accepted', title: t('accepted'), orders: acceptedOrders, context: 'info' },
    { key: 'started', title: t('started'), orders: startedOrders, context: 'info' },
    { key: 'ready', title: t('ready'), orders: readyOrders, context: 'info' },
    { key: 'fulfilled', title: t('fulfilled'), orders: fulfilledOrders, context: 'success' },
    { key: 'cancelled', title: t('cancelled'), orders: cancelledOrders, context: 'danger' },
  ]

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
    (<div className="FoodtechDashboard">
      <div className="FoodtechDashboard__Navbar">
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
        <div>
          <AntdConfigProvider>
            <DatePicker
              format={ 'll' }
              defaultValue={ moment(date) }
              onChange={ (date) => {
                if (date) {
                  onDateChange(date)
                }
              } } />
          </AntdConfigProvider>
        </div>
      </div>
      <div>
        { showSettings && (
            <div className="FoodtechDashboard__Navbar__Slider">
              <Row type="flex" align="middle" className='px-2'>
                <Col span={ 4 }>
                    <span>
                      <i className="fa fa-clock-o mr-1"></i>
                      { t('RESTAURANT_DASHBOARD_DELAY_SETTING') }
                      <Tooltip title={ t('RESTAURANT_DASHBOARD_DELAY_SETTING_HELP')}>
                        <i className='ml-1 fa fa-question-circle'></i>
                      </Tooltip>
                    </span>
                </Col>
                <Col span={ 20 }>
                  <Slider
                    max={ 120 }
                    defaultValue={ preparationDelay }
                    marks={ sliderMarks }
                    step={ null }
                    onChange={ delay => dispatch(setPreparationDelay(delay)) }
                    tooltip={{
                      formatter: _tipFormatter
                    }} />
                </Col>
              </Row>
            </div>
          ) }
      </div>
      <div className="FoodtechDashboard__SlotViz">
        <SlotViz />
      </div>
      <div className="FoodtechDashboard__Columns">
        { sections.map(section => (
          <Column
            key={ section.key }
            id={ section.key }
            orders={ section.orders }
            title={ section.title }
            context={ section.context }
            active={ activeTab === section.key }
            onCardClick={ order => dispatch(setCurrentOrder(order)) } />
        )) }
      </div>
      <nav className="FoodtechDashboard__TabNav">
        { sections.map(section => (
          <Tab
            key={ section.key }
            title={ `${ section.title } (${ section.orders.length })` }
            target={ section.key }
            onClick={ (tab) => dispatch(setActiveTab(tab)) }
            active={ activeTab === section.key } />
        ))}
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
    </div>)
  );
}
