import React from 'react';
import {render} from 'react-dom';
import OrderFollow from './OrderFollow.jsx';

var _ = require('underscore');
var moment = require('moment');
var MapHelper = require('../MapHelper');

var map;
var courierMarker;
var orderFollow;

var hostname = window.location.hostname;
var socket;

const order = window.AppData.order;
const delivery = order.delivery;

moment.locale($('html').attr('lang'));

function startWebSocket() {
  socket = io('//' + hostname, { path: '/tracking/socket.io' })

  socket.on('tracking', event => {
    if (delivery.courier == event.user) {
      if (!courierMarker) {
        courierMarker = MapHelper.createMarker(event.coords, 'bicycle', 'circle', '#337ab7')
        courierMarker.addTo(map)
        MapHelper.fitToLayers(map, [ restaurantMarker, deliveryAddressMarker, courierMarker ])
      } else {
        courierMarker
          .setLatLng(event.coords)
          .update()
      }
    }
  });

  socket.on('delivery_events', event => {
    if (event.delivery === delivery.id) {
      orderFollow.handleDeliveryEvent(event)
    }
  });

  socket.on('order_events', event => {
    if (event.order === order.id) {
      orderFollow.handleOrderEvent(event)
    }
  });
}

// --------------------
// Initialize LeafletJS
// --------------------

if ($('#map').is(':visible')) {

  // Render React element first
  orderFollow = render(
    <OrderFollow
      order={window.AppData.order}
      orderEvents={window.AppData.order_events}
      deliveryEvents={window.AppData.delivery_events}
    />,
    document.getElementById('order-follow')
  );

  map = MapHelper.init('map');

  var restaurant = {
    lat: delivery.originAddress.geo.latitude,
    lng: delivery.originAddress.geo.longitude,
  };
  var deliveryAddress = {
    lat: delivery.deliveryAddress.geo.latitude,
    lng: delivery.deliveryAddress.geo.longitude,
  };

  var restaurantMarker = MapHelper.createMarker(restaurant, 'cutlery', 'marker', '#337ab7');
  var deliveryAddressMarker = MapHelper.createMarker(deliveryAddress, 'user', 'marker', '#337ab7');

  MapHelper.getPolyline(restaurantMarker, deliveryAddressMarker)
    .then((data) => {

      restaurantMarker.addTo(map);
      deliveryAddressMarker.addTo(map);

      var polyline = new L.Polyline(data, {
        color: '#337ab7',
        weight: 3,
        opacity: 0.8,
        smoothFactor: 1
      });
      map.addLayer(polyline);

      MapHelper.fitToLayers(map, [restaurantMarker, deliveryAddressMarker, polyline], 1);

      if (delivery.status !== 'DELIVERED' && delivery.status !== 'CANCELED') {
        startWebSocket();
      }
    });

}
