import React from 'react';
import {render} from 'react-dom';
import OrderFollow from './OrderFollow.jsx';

var _ = require('underscore');
var moment = require('moment');
var MapHelper = require('../MapHelper');

var map;
var courierMarker;
var orderFollow;

var center = {
  lat: 48.857498,
  lng: 2.335402
};
var zoom = window.mapZoom || 13;

var hostname = window.location.hostname;
var socket;

const order = window.AppData.order;
const delivery = order.delivery;

moment.locale($('html').attr('lang'));

function startWebSocket() {
  socket = io('//' + hostname, {path: '/order-tracking/socket.io'});

  socket.on('connect', function() {
    socket.emit('delivery', delivery);
  });

  socket.on('courier', function (data) {
    if (!courierMarker) {
      courierMarker = MapHelper.createMarker(data.coords, 'bicycle', 'circle', '#337ab7');
      courierMarker.addTo(map);

      MapHelper.fitToLayers(map, [restaurantMarker, deliveryAddressMarker, courierMarker]);
    } else {
      courierMarker.setLatLng([data.coords.lat, data.coords.lng]).update();
    }
  });

  socket.on('delivery_event', function (data) {
    orderFollow.handleDeliveryEvent(data);
  });

  socket.on('order_event', function (data) {
    orderFollow.handleOrderEvent(data);
  });
}

// --------------------
// Initialize LeafletJS
// --------------------

map = MapHelper.init('map', center, zoom, false);

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

    MapHelper.fitToLayers(map, [restaurantMarker, deliveryAddressMarker], 1);

    var polyline = new L.Polyline(data, {
      color: '#337ab7',
      weight: 3,
      opacity: 0.8,
      smoothFactor: 1
    });
    map.addLayer(polyline);

    if (delivery.status !== 'DELIVERED' && delivery.status !== 'CANCELED') {
      startWebSocket();
    }
  });


console.log(window.__order_events);

orderFollow = render(
  <OrderFollow
    order={window.AppData.order}
    orderEvents={window.AppData.order_events}
    deliveryEvents={window.AppData.delivery_events}
  />,
  document.getElementById('order-follow')
);
