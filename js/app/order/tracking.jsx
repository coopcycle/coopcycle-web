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

const order = window.__order;
const delivery = order.delivery;

moment.locale($('html').attr('lang'));

function startWebSocket() {
  socket = io('//' + hostname, {path: '/order-tracking/socket.io'});

  socket.on('connect', function() {
    socket.emit('delivery', delivery);
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

  socket.on('delivery_event', function (data) {
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
  lat: delivery.originAddress.geo.latitude,
  lng: delivery.originAddress.geo.longitude,
};
var deliveryAddress = {
  lat: delivery.deliveryAddress.geo.latitude,
  lng: delivery.deliveryAddress.geo.longitude,
};

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

    if (delivery.status !== 'DELIVERED' && delivery.status !== 'CANCELED') {
      startWebSocket();
    }
  });

orderEvents = render(
  <OrderEvents
    i18n={window.__i18n}
    events={window.__delivery_events} />,
  document.getElementById('order-events')
);
