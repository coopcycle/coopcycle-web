import React from 'react';
import { render } from 'react-dom';
import 'whatwg-fetch';
import OrderList from './OrderList.jsx'
import OrderDetails from './OrderDetails.jsx'

const hostname = window.location.hostname;
const restaurant = window.__restaurant;
const restaurantId = restaurant['@id'].replace('/api/restaurants/', '')

let orderList;
let orderDetails;

setTimeout(function() {

  var socket = io('//' + hostname, {path: '/restaurant-panel/socket.io'});

  socket.on('connect', () => socket.emit('restaurant', restaurant));
  socket.on('order', data => orderList.addOrder(data));

}, 1000);

orderList = render(
  <OrderList
    orders={ window.__orders }
    active={ window.__order }
    i18n={ window.__i18n }
    onOrderClick={ (order) => {
      orderList.setActive(order)
      orderDetails.setOrder(order)

      const orderId = order['@id'].replace('/api/orders/', '')

      const url = window.__routes['restaurant_order']
        .replace('__RESTAURANT_ID__', restaurantId)
        .replace('__ORDER_ID__', orderId)

      window.history.pushState({}, '', url)

    } } />,
  document.getElementById('order-list')
);

orderDetails = render(
  <OrderDetails
    order={ window.__order }
    routes={ window.__routes }
    i18n={ window.__i18n }
    onClose={ () => {
      orderList.setActive(null)
      orderDetails.setOrder(null)

      const url = window.__routes['restaurant_orders']
        .replace('__RESTAURANT_ID__', restaurantId)

      window.history.pushState({}, '', url)

    } } />,
  document.querySelector('.restaurant-dashboard__details')
);
