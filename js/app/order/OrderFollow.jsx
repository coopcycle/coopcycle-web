import React from 'react';
import moment from 'moment';

moment.locale($('html').attr('lang'));

class OrderFollow extends React.Component {

  constructor(props) {
    super(props)
    this.state = {
      order: this.props.order,
    }
  }

  updateOrder(order) {
    this.setState({ order })
  }

  render () {

    const { order } = this.state

    const hasBeenAccepted  = order.state === 'accepted'
    const hasBeenPrepared  = order.state === 'ready'
    const hasBeenDelivered = false
    const isWaitingPreparation = !hasBeenPrepared && hasBeenAccepted;
    const isWaitingForDelivery = hasBeenPrepared && !hasBeenDelivered

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
        <div className="order-follow">
          <div className={ hasBeenAccepted ? "order-follow--step  order-follow--step__done" : "order-follow--step"} style={ !hasBeenAccepted ? { opacity: 1} : {} }>
            <span className="order-follow--number">1</span>
            <span className="order-follow--title">
              { hasBeenAccepted ? "Commande validée" : "Commande en attente de validation" }
            </span>
            <div className="order-follow--description" style={ !hasBeenAccepted ? { display: 'block'} : {} }>
              Le restaurateur doit valider votre commande. Dans le cas où votre commande serait refusée vous ne serez pas débité.
            </div>
          </div>
          <div className={ hasBeenPrepared ? "order-follow--step order-follow--step__done" : "order-follow--step" } style={ isWaitingPreparation ? { opacity: 1} : {} }>
            <span className="order-follow--number">2</span>
            <span className="order-follow--title">
              { hasBeenPrepared ? "Commande prête" : "Commande en préparation" }
            </span>
            <div className="order-follow--description" style={ isWaitingPreparation ? { display: 'block'} : {} }>
              Votre commande est en train d'être préparée avec le plus grand soin.
            </div>
          </div>
          <div className={ hasBeenDelivered ? "order-follow--step order-follow--step__done" : "order-follow--step" }  style={ isWaitingForDelivery ? { opacity: 1} : {} }>
            <span className="order-follow--number">3</span>
            <span className="order-follow--title">
              Livraison en cours
            </span>
            <div className="order-follow--description" style={ isWaitingForDelivery ? { display: 'block'} : {} }>
              Votre commande est prête - votre livreur arrive!
            </div>
          </div>
          <div className={ hasBeenDelivered ? "order-follow--step order-follow--step__done" : "order-follow--step" } style={ hasBeenDelivered ? { opacity: 1} : {} }>
            <span className="order-follow--number">4</span>
            <span className="order-follow--title">
              Commande livrée!
            </span>
            <div className="order-follow--description" style={ hasBeenDelivered ? { display: 'block'} : {} }>
              Régalez vous!
            </div>
          </div>
        </div>
      </div>
    )

  }
}

export default OrderFollow
