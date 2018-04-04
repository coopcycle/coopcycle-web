import React from 'react';
import {render} from 'react-dom';
import OrderFollow from './OrderFollow.jsx';
import _ from 'lodash'

var moment = require('moment');
var MapHelper = require('../MapHelper');

moment.locale($('html').attr('lang'));

var map;
var courierMarker;
var orderFollow;

const order = window.AppData.order

function startWebSocket() {

  const socket = io('//' + window.location.hostname, { path: '/tracking/socket.io' })

  // socket.on('tracking', event => {
  //   if (delivery.courier == event.user) {
  //     if (!courierMarker) {
  //       courierMarker = MapHelper.createMarker(event.coords, 'bicycle', 'circle', '#337ab7')
  //       courierMarker.addTo(map)
  //       MapHelper.fitToLayers(map, [ restaurantMarker, deliveryAddressMarker, courierMarker ])
  //     } else {
  //       courierMarker
  //         .setLatLng(event.coords)
  //         .update()
  //     }
  //   }
  // });

  socket.on(`order:${order.id}:state_changed`, order => orderFollow.updateOrder(order))

}

// --------------------
// Initialize LeafletJS
// --------------------

if ($('#map').is(':visible')) {

  // Render React element first
  orderFollow = render(
    <OrderFollow order={ window.AppData.order } />,
    document.getElementById('order-follow')
  );

  map = MapHelper.init('map');

  var restaurantMarker = MapHelper.createMarker(order.restaurant.address.latlng, 'cutlery', 'marker', '#337ab7');
  var deliveryAddressMarker = MapHelper.createMarker(order.shippingAddress.latlng, 'user', 'marker', '#337ab7');

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

      if (!_.includes(['cancelled', 'fulfilled', 'refused'], order.state)) {
        startWebSocket()
      }

    });
}
