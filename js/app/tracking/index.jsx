import React from 'react';
import {render} from 'react-dom';
import OrderList from './OrderList.jsx';

var _ = require('underscore');
var TWEEN = require('tween.js');

var MarkerIcons = {
  "CUTLERY":"M23.04-62.135v23.04q0 2.196-1.278 3.996t-3.33 2.52v28.044q0 1.872-1.368 3.24t-3.24 1.368h-4.608q-1.872 0-3.24-1.368t-1.368-3.24v-28.044q-2.052-.72-3.33-2.52t-1.278-3.996v-23.04q0-.936.684-1.62t1.62-.684 1.62.684.684 1.62v14.976q0 .936.684 1.62t1.62.684 1.62-.684.684-1.62v-14.976q0-.936.684-1.62t1.62-.684 1.62.684.684 1.62v14.976q0 .936.684 1.62t1.62.684 1.62-.684.684-1.62v-14.976q0-.936.684-1.62t1.62-.684 1.62.684.684 1.62zm27.648 0v57.6q0 1.872-1.368 3.24t-3.24 1.368h-4.608q-1.872 0-3.24-1.368t-1.368-3.24v-18.432h-8.064q-.468 0-.81-.342t-.342-.81v-28.8q0-4.752 3.384-8.136t8.136-3.384h9.216q.936 0 1.62.684t.684 1.62z",
  "MAP_MARKER":"M27.648-41.399q0-3.816-2.7-6.516t-6.516-2.7-6.516 2.7-2.7 6.516 2.7 6.516 6.516 2.7 6.516-2.7 2.7-6.516zm9.216 0q0 3.924-1.188 6.444l-13.104 27.864q-.576 1.188-1.71 1.872t-2.43.684-2.43-.684-1.674-1.872l-13.14-27.864q-1.188-2.52-1.188-6.444 0-7.632 5.4-13.032t13.032-5.4 13.032 5.4 5.4 13.032z",
  "BICYCLE":"M27.432-22.967h-11.304q-1.44 0-2.07-1.26t.234-2.412l6.768-9.036q-2.34-1.116-4.932-1.116-4.752 0-8.136 3.384t-3.384 8.136 3.384 8.136 8.136 3.384q4.14 0 7.308-2.61t3.996-6.606zm-6.696-4.608h6.696q-.648-3.06-2.7-5.328zm17.28 0l10.368-13.824h-17.28l-3.564 4.752q3.78 3.708 4.536 9.072h5.94zm40.32 2.304q0-4.752-3.384-8.136t-8.136-3.384q-2.16 0-4.356.864l6.264 9.36q.54.828.36 1.764t-.972 1.44q-.54.396-1.296.396-1.26 0-1.908-1.044l-6.264-9.36q-3.348 3.42-3.348 8.1 0 4.752 3.384 8.136t8.136 3.384 8.136-3.384 3.384-8.136zm4.608 0q0 6.66-4.734 11.394t-11.394 4.734-11.394-4.734-4.734-11.394q0-3.492 1.422-6.606t3.942-5.382l-2.34-3.528-12.708 16.884q-.648.936-1.836.936h-7.092q-.828 5.904-5.364 9.864t-10.584 3.96q-6.66 0-11.394-4.734t-4.734-11.394 4.734-11.394 11.394-4.734q4.104 0 7.74 1.98l4.932-6.588h-8.064q-.936 0-1.62-.684t-.684-1.62.684-1.62 1.62-.684h13.824v4.608h15.66l-3.06-4.608h-7.992q-.936 0-1.62-.684t-.684-1.62.684-1.62 1.62-.684h9.216q1.188 0 1.908 1.008l9.612 14.4q3.276-1.584 6.912-1.584 6.66 0 11.394 4.734t4.734 11.394z"
}

var map;

var orders = [];
var couriers = [];
var deliveryAddresses = [];
var orderList;

var LABELS = {
  'WAITING': 'label-default',
  'DELIVERING': 'label-success',
  'PICKED': 'label-info',
  'DELIVERED': 'label-success'
}

var COLORS = {
  TURQUOISE: '#1ABC9C',
  GREEN_SEA: '#16A085',
  EMERALD: '#2ECC71',
  NEPHRITIS: '#27AE60',
  PETER_RIVER: '#3498DB',
  BELIZE_HOLE: '#2980B9',
  AMETHYST: '#9B59B6',
  WISTERIA: '#8E44AD',
  SUN_FLOWER: '#F1C40F',
  ORANGE: '#F39C12',
  CARROT: '#E67E22',
  PUMPKIN: '#D35400',
  ALIZARIN: '#E74C3C',
  POMEGRANATE: '#C0392B',
}

var infoWindows = [];
var center = {
  lat: 48.857498,
  lng: 2.335402
};
var zoom = window.mapZoom || 13;

function createMarkerIcon(iconPath, iconFillColor) {
  return {
    path: iconPath,
    origin: new google.maps.Point(0, 0),
    anchor: new google.maps.Point(30, -30),
    scale: 0.4,
    strokeWeight: 0,
    strokeColor: '#000',
    strokeOpacity: 1,
    fillColor: iconFillColor,
    fillOpacity: 1,
  }
}

function createMarker(position, iconPath, iconFillColor, infoWindow) {
  var marker = new google.maps.Marker({
    position: position,
    map: map,
    animation: google.maps.Animation.DROP,
    icon: createMarkerIcon(iconPath, iconFillColor),
  });

  if (infoWindow) {
    marker.addListener('click', function() {
      closeAllInfoWindows();
      infoWindow.open(map, marker);
    });
  }

  return marker;
}

function createCircle(position, color) {

  var circle = new google.maps.Circle({
    strokeColor: color,
    strokeOpacity: 0.35,
    strokeWeight: 1,
    fillColor: color,
    fillOpacity: 0.15,
    map: map,
    center: position,
    radius: 0
  });

  return circle;
}

function addOrder(order) {

  var key = order.key;
  var position = order.coords;

  var randomColor = COLORS[_.first(_.shuffle(_.keys(COLORS)))];

  var marker = _.find(orders, function(marker) {
    return marker.key === key;
  });

  if (!marker) {

    var circle = createCircle(order.restaurant, randomColor);

    if (order.state === 'WAITING' || order.state === 'DISPATCHING') {
      var coords = { radius: 0 };
      var tween = new TWEEN.Tween(coords)
        .easing(TWEEN.Easing.Cubic.Out)
        .to({ radius: 1000 }, 500)
        .onUpdate(function() {
            circle.setRadius(this.radius)
        })
        .delay(50)
        .repeat(Infinity)
        .yoyo(true)
        .start();

      var animate = function animate(time) {
        requestAnimationFrame(animate);
        TWEEN.update(time);
      }

      requestAnimationFrame(animate);
    }

    var infoWindow = new google.maps.InfoWindow({
      content: order.key
    });
    infoWindows.push(infoWindow);

    orderList.setItem(key, Object.assign(order, {
      color: randomColor
    }));

    marker = {
      key: key,
      courier: order.courier,
      color: randomColor,
      restaurantMarker: createMarker(order.restaurant, MarkerIcons.CUTLERY, randomColor, infoWindow),
      deliveryAddressMarker: createMarker(order.deliveryAddress, MarkerIcons.MAP_MARKER, randomColor),
      restaurantCircle: circle,
      circleTween: tween,
    };

    orders.push(marker);
  } else {
    marker.courier = order.courier;
    if (order.state === 'DELIVERING') {
      // TODO Do not call this every time!
      if (marker.circleTween) {
        marker.circleTween.stop();
      }
      marker.restaurantCircle.setRadius(0);
      marker.restaurantCircle.setMap(null);
    }

    orderList.setItem(key, order);
  }
}

function addCourier(key, position) {

  var marker = _.find(couriers, function(marker) {
    return marker.key === key;
  });

  var order = _.find(orders, function(order) {
    return order.courier === key;
  });

  var color = order ? order.color : '#000';

  if (!marker) {

    var infoWindow = new google.maps.InfoWindow({
      content: key
    });
    infoWindows.push(infoWindow);

    marker = {
      key: key,
      marker: createMarker(position, MarkerIcons.BICYCLE, color),
    };

    marker.marker.addListener('click', function() {
      closeAllInfoWindows();
      infoWindow.open(map, marker.marker);
    });

    couriers.push(marker);
  } else {
    marker.marker.setIcon(createMarkerIcon(MarkerIcons.BICYCLE, color));
    marker.marker.setPosition(position);
  }
}

function closeAllInfoWindows() {
  _.each(infoWindows, function(infoWindow) {
    infoWindow.close();
  });
}

function removeMissingObjects(objects, markers) {
  var allKeys = _.map(objects, function(object) {
    return object.key;
  });
  var onMapKeys = _.map(markers, function(marker) {
    return marker.key;
  });
  var keysToRemove = _.difference(onMapKeys, allKeys);

  removeMarkersByKeys(keysToRemove, markers);
}

function removeMarkersByKeys(keys, markers) {
  _.each(keys, function(key) {
    var index = _.findIndex(markers, function(marker) {
      return marker.key === key;
    });
    if (-1 !== index) {
      console.log('Removing marker ' + key);
      var marker = markers.splice(index, 1);
      if (marker[0].hasOwnProperty('marker')) {
        marker[0].marker.setMap(null);
      }
      if (marker[0].hasOwnProperty('restaurantMarker')) {
        marker[0].restaurantMarker.setMap(null);
      }
      if (marker[0].hasOwnProperty('deliveryAddressMarker')) {
        marker[0].deliveryAddressMarker.setMap(null);
      }
      if (marker[0].hasOwnProperty('restaurantCircle')) {
        marker[0].restaurantCircle.setRadius(0);
        marker[0].restaurantCircle.setMap(null);
      }
      orderList.removeItem(key);
    }
  });
}

window.initMap = function() {
  map = new google.maps.Map(document.getElementById('map'), {
    center: center,
    zoom: zoom
  });

  var hostname = window.location.hostname;

  setTimeout(function() {

    var socket = io('//' + hostname, {path: '/tracking/socket.io'});

    socket.on('couriers', function (data) {
      removeMissingObjects(data, couriers);
      for (var i = 0; i < data.length; i++) {
        addCourier(data[i].key, data[i].coords);
      }
    });

    socket.on('orders', function (data) {
      removeMissingObjects(data, orders);
      for (var i = 0; i < data.length; i++) {
        addOrder(data[i]);
      }
    });

  }, 1000);
}

orderList = render(
  <OrderList
    onItemClick={(order) => {
      console.log('Zoom to order', order.key);

      var marker = _.find(orders, function(marker) {
        return marker.key === order.key;
      });

      var bounds = new google.maps.LatLngBounds();

      bounds.extend(marker.restaurantMarker.position);
      bounds.extend(marker.deliveryAddressMarker.position);

      if (marker.courier) {
        var courierMarker = _.find(couriers, function(marker) {
          return marker.key === order.courier;
        });
        if (courierMarker) {
          bounds.extend(courierMarker.marker.position);
        }
      }

      map.fitBounds(bounds);
      google.maps.event.addListenerOnce(map, 'bounds_changed', function() {
        map.setCenter(bounds.getCenter());
      });

    }} />,
  document.getElementById('order-list')
);
