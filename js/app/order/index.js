import OrderTimeline from '../widgets/OrderTimeline'

const el = document.getElementById('order-timeline')

import './index.scss'

if (el) {

  const order = JSON.parse(el.dataset.order)
  const events = JSON.parse(el.dataset.events)

  new OrderTimeline(el, {
    order,
    events,
    centrifugo: {
      channel: el.dataset.centrifugoChannel,
      token: el.dataset.centrifugoToken
    }
  })
}
