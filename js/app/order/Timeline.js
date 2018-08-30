import React, { Component } from 'react';
import moment from 'moment';
import i18n from '../i18n'
import TimelineStep from './TimelineStep'

moment.locale($('html').attr('lang'));

export default class extends React.Component {

  constructor(props) {
    super(props)

    const { order } = this.props

    this.state = {
      order: order,
      step: this.getStep(order)
    }
  }

  getStep(order) {
    if (order.state === 'accepted') {
      return 1
    } else if (order.state === 'ready') {
      return 2
    } else if (order.state === 'fulfilled') {
      return 3
    } else {
      return 0
    }
  }

  updateOrder(order) {
    this.setState({ order, step: this.getStep(order) })
  }

  renderOrderTimeline(step) {
    return (
      <div className="order-follow">
        <TimelineStep
          number="1"
          active={ step === 0 }
          done={ step > 0 }
          title={ step > 0 ? i18n.t('ORDER_FOLLOW_STEP_1_ORDER_VALIDATED') : i18n.t('ORDER_FOLLOW_STEP_1_ORDER_IN_VALIDATION') }
          description={ i18n.t('ORDER_FOLLOW_STEP_1_DESCRIPTION') } />
        <TimelineStep
          number="2"
          active={ step === 1 }
          done={ step > 1 }
          title={ step > 1 ? i18n.t('ORDER_FOLLOW_STEP_2_ORDER_READY') : i18n.t('ORDER_FOLLOW_STEP_2_ORDER_IN_PREPARATION') }
          description={ i18n.t('ORDER_FOLLOW_STEP_2_DESCRIPTION') } />
        <TimelineStep
          number="3"
          active={ step === 2 }
          done={ step > 2 }
          title={ i18n.t('ORDER_FOLLOW_STEP_3_TITLE') }
          description={ i18n.t('ORDER_FOLLOW_STEP_3_DESCRIPTION') } />
        <TimelineStep
          number="4"
          active={ step === 3 }
          done={ step > 3 }
          title={ i18n.t('ORDER_FOLLOW_STEP_4_TITLE') }
          description={ i18n.t('ORDER_FOLLOW_STEP_4_DESCRIPTION') } />
      </div>
    )
  }

  renderOrderRefused () {
    return (
      <div className="alert alert-danger">
        Votre commande ne peut pas être honorée par le restaurateur.
      </div>
    )
  }

  renderOrderCancelled () {
    return (
      <div className="alert alert-danger">
        Votre commande a été annulée.
      </div>
    )
  }

  render () {

    const { order, step } = this.state

    const deliveryMoment = moment(order.shippedAt)
    const deliveryTime = deliveryMoment.format('LT')
    const formattedDeliveryDate = deliveryMoment.format('dddd D MMMM')
    const deliveryIsToday = formattedDeliveryDate === moment(Date.now()).format('dddd D MMMM')

    return (
      <div>
        <p>
          {deliveryIsToday ? i18n.t("ORDER_FOLLOW_DELIVERY_EXPECTED_AT", {deliveryTime: deliveryTime}) : i18n.t("ORDER_FOLLOW_DELIVERY_EXPECTED_AT_WITH_DATE", {deliveryTime: deliveryTime, deliveryDate: formattedDeliveryDate})}
        </p>
        <hr/>
        { order.state === 'refused' && this.renderOrderRefused()}
        { order.state === 'cancelled' && this.renderOrderCancelled()}
        { order.state !== 'cancelled' && order.state !== 'refused' && this.renderOrderTimeline(step)}
      </div>
    )
  }
}
