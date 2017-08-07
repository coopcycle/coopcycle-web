import React from 'react';
import {render} from 'react-dom';
import OrderEvents from './OrderEvents.jsx';

var _ = require('underscore');
var moment = require('moment');
var MapHelper = require('../MapHelper');

var map;
var courierMarker;
var orderEvents;

var center = {
  lat: 48.857498,
  lng: 2.335402
};
var zoom = window.mapZoom || 13;

var hostname = window.location.hostname;
var socket;

var order = window.__order;

moment.locale($('html').attr('lang'));

function startWebSocket() {
  socket = io('//' + hostname, {path: '/order-tracking/socket.io'});

  socket.on('connect', function() {
    socket.emit('order', order);
  });

  socket.on('courier', function (data) {
    if (!courierMarker) {
      courierMarker = MapHelper.createMarker(data.coords, 'bicycle', 'circle', '#fff');
      courierMarker.addTo(map);

      MapHelper.fitToLayers(map, [restaurantMarker, deliveryAddressMarker, courierMarker]);
    } else {
      courierMarker.setLatLng([data.coords.lat, data.coords.lng]).update();
    }
  });

  socket.on('order_event', function (data) {
    orderEvents.add({
      eventName: data.status,
      timestamp: data.timestamp
    });
  });
}

// --------------------
// Initialize LeafletJS
// --------------------

map = MapHelper.init('map', center, zoom);

var restaurant = {
  lat: order.restaurant.address.geo.latitude,
  lng: order.restaurant.address.geo.longitude,
}
var deliveryAddress = {
  lat: order.delivery.deliveryAddress.geo.latitude,
  lng: order.delivery.deliveryAddress.geo.longitude,
}

var restaurantMarker = MapHelper.createMarker(restaurant, 'cutlery', 'marker', '#fff');
var deliveryAddressMarker = MapHelper.createMarker(deliveryAddress, 'user', 'marker', '#fff');

MapHelper.getPolyline(restaurantMarker, deliveryAddressMarker)
  .then((data) => {

    restaurantMarker.addTo(map);
    deliveryAddressMarker.addTo(map);

    MapHelper.fitToLayers(map, [restaurantMarker, deliveryAddressMarker]);

    var polyline = new L.Polyline(data, {
      color: '#fff',
      weight: 3,
      opacity: 0.8,
      smoothFactor: 1
    });
    map.addLayer(polyline);

    if (order.status !== 'DELIVERED' && order.status !== 'CANCELED') {
      startWebSocket();
    }
  });

orderEvents = render(
  <OrderEvents
    i18n={window.__i18n}
    events={window.__order_events} />,
  document.getElementById('order-events')
);
