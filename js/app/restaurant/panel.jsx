import React from 'react'
import { render } from 'react-dom'
import OrderList from './OrderList.jsx'
import OrderDetails from './OrderDetails.jsx'

const hostname = window.location.hostname
const restaurant = window.__restaurant

let orderList
let orderDetails

setTimeout(function() {

  var socket = io('//' + hostname, { path: '/tracking/socket.io' })
  socket.on(`restaurant:${restaurant.id}:orders`, order => orderList.addOrder(order))

}, 1000)

orderList = render(
  <OrderList
    orders={ window.__orders }
    active={ window.__order }
    i18n={ window.__i18n }
    onOrderClick={ (order) => {
      orderList.setActive(order)
      orderDetails.setOrder(order)

      const url = window.__routes['dashboard_order']
        .replace('__RESTAURANT_ID__', restaurant.id)
        .replace('__ORDER_ID__', order.id)

      window.history.pushState({}, '', url)

    } } />,
  document.getElementById('order-list')
)

orderDetails = render(
  <OrderDetails
    restaurant={ window.__restaurant }
    order={ window.__order }
    routes={ window.__routes }
    i18n={ window.__i18n }
    onClose={ () => {
      orderList.setActive(null)
      orderDetails.setOrder(null)

      const url = window.__routes['dashboard']
        .replace('__RESTAURANT_ID__', restaurant.id)

      window.history.pushState({}, '', url)

    } } />,
  document.querySelector('.restaurant-dashboard__details')
)
