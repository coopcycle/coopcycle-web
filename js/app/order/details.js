import OrderTimeline from '../widgets/OrderTimeline'
import storage from '../search/address-storage'

import './details.scss'

const timelineEl = document.getElementById('order-timeline')
const checkoutResetEl = document.getElementById('checkout-reset')

if (timelineEl) {

  const order = JSON.parse(timelineEl.dataset.order)
  const events = JSON.parse(timelineEl.dataset.events)
  const orderAccessToken = timelineEl.dataset.orderAccessToken

  new OrderTimeline(timelineEl, {
    order,
    orderAccessToken,
    events,
    centrifugo: {
      channel: timelineEl.dataset.centrifugoChannel,
      token: timelineEl.dataset.centrifugoToken
    }
  })
}

if (checkoutResetEl) {
  storage.remove('search_address')
  storage.remove('search_geohash')
}
