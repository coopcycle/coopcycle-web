import React from 'react';
import moment from 'moment';
import i18n from '../i18n'

moment.locale($('html').attr('lang'));

class OrderFollow extends React.Component {

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

  renderOrderFollow(step) {
    return (
      <div className="order-follow">
        <div className={ step > 0 ? "order-follow--step  order-follow--step__done" : "order-follow--step"} style={ step === 0 ? { opacity: 1} : {} }>
          <span className="order-follow--number">1</span>
          <span className="order-follow--title">
              { step > 0 ? i18n.t('ORDER_FOLLOW_STEP_1_ORDER_VALIDATED') : i18n.t('ORDER_FOLLOW_STEP_1_ORDER_IN_VALIDATION') }
            </span>
          <div className="order-follow--description" style={ step === 0 ? { display: 'block'} : {} }>
            {i18n.t('ORDER_FOLLOW_STEP_1_DESCRIPTION')}
          </div>
        </div>
        <div className={ step > 1 ? "order-follow--step order-follow--step__done" : "order-follow--step" } style={ step === 1 ? { opacity: 1} : {} }>
          <span className="order-follow--number">2</span>
          <span className="order-follow--title">
              { step > 1 ? i18n.t('ORDER_FOLLOW_STEP_2_ORDER_READY') : i18n.t('ORDER_FOLLOW_STEP_2_ORDER_IN_PREPARATION') }
            </span>
          <div className="order-follow--description" style={ step === 1 ? { display: 'block'} : {} }>
            {i18n.t('ORDER_FOLLOW_STEP_2_DESCRIPTION')}
          </div>
        </div>
        <div className={ step === 3 ? "order-follow--step order-follow--step__done" : "order-follow--step" }  style={ step === 2 ? { opacity: 1} : {} }>
          <span className="order-follow--number">3</span>
          <span className="order-follow--title">
              {i18n.t('ORDER_FOLLOW_STEP_3_TITLE')}
            </span>
          <div className="order-follow--description" style={ step === 2 ? { display: 'block'} : {} }>
            {i18n.t('ORDER_FOLLOW_STEP_3_DESCRIPTION')}
          </div>
        </div>
        <div className={ step === 3 ? "order-follow--step order-follow--step__done" : "order-follow--step" } style={ step === 3 ? { opacity: 1} : {} }>
          <span className="order-follow--number">4</span>
          <span className="order-follow--title">
              {i18n.t('ORDER_FOLLOW_STEP_4_TITLE')}
            </span>
          <div className="order-follow--description" style={ step === 3 ? { display: 'block'} : {} }>
            {i18n.t('ORDER_FOLLOW_STEP_4_DESCRIPTION')}
          </div>
        </div>
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
    const deliveryTime = deliveryMoment.format('HH[h]mm')
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
        { order.state !== 'cancelled' && order.state !== 'refused' && this.renderOrderFollow(step)}
      </div>
    )

  }
}

export default OrderFollow
