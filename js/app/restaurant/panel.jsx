import React from 'react';
import { render } from 'react-dom';
import 'whatwg-fetch';
import OrderList from './OrderList.jsx'

const hostname = window.location.hostname;
const restaurant = window.__restaurant;

let orderList;

setTimeout(function() {

  var socket = io('//' + hostname, {path: '/restaurant-panel/socket.io'});

  socket.on('connect', function() {
    socket.emit('restaurant', restaurant);
  });

  socket.on('order', function (data) {
    console.log(data);
    orderList.addOrder(data);
  });

}, 1000);

orderList = render(
  <OrderList
    orders={ window.__orders } />,
  document.getElementById('order-list')
);
