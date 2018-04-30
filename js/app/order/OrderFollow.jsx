import React from 'react';
import moment from 'moment';

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
              { step > 0 ? "Commande validée" : "Commande en attente de validation" }
            </span>
          <div className="order-follow--description" style={ step === 0 ? { display: 'block'} : {} }>
            Le restaurateur doit valider votre commande. Dans le cas où votre commande serait refusée vous ne serez pas débité.
          </div>
        </div>
        <div className={ step > 1 ? "order-follow--step order-follow--step__done" : "order-follow--step" } style={ step === 1 ? { opacity: 1} : {} }>
          <span className="order-follow--number">2</span>
          <span className="order-follow--title">
              { step > 1 ? "Commande prête" : "Commande en préparation" }
            </span>
          <div className="order-follow--description" style={ step === 1 ? { display: 'block'} : {} }>
            Votre commande est en train d'être préparée avec le plus grand soin.
          </div>
        </div>
        <div className={ step === 3 ? "order-follow--step order-follow--step__done" : "order-follow--step" }  style={ step === 2 ? { opacity: 1} : {} }>
          <span className="order-follow--number">3</span>
          <span className="order-follow--title">
              Livraison en cours
            </span>
          <div className="order-follow--description" style={ step === 2 ? { display: 'block'} : {} }>
            Votre commande est prête - votre livreur arrive!
          </div>
        </div>
        <div className={ step === 3 ? "order-follow--step order-follow--step__done" : "order-follow--step" } style={ step === 3 ? { opacity: 1} : {} }>
          <span className="order-follow--number">4</span>
          <span className="order-follow--title">
              Commande livrée!
            </span>
          <div className="order-follow--description" style={ step === 3 ? { display: 'block'} : {} }>
            Régalez vous!
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

    let deliveryDateText = !deliveryIsToday ? ' le ' + formattedDeliveryDate : '';

    return (
      <div>
        <p>
          Livraison prévue à { deliveryTime }{ deliveryDateText }.
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
