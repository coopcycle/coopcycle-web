import OrderTimeline from '../widgets/OrderTimeline'
import storage from '../search/address-storage'

import './index.scss'

const timelineEl = document.getElementById('order-timeline')
const checkoutResetEl = document.getElementById('checkout-reset')

if (timelineEl) {

  const order = JSON.parse(timelineEl.dataset.order)
  const events = JSON.parse(timelineEl.dataset.events)

  new OrderTimeline(timelineEl, {
    order,
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
