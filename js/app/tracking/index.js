var MarkerIcons = require('fontawesome-markers');
var _ = require('underscore');
var TWEEN = require('tween.js');

var map;

var orders = [];
var couriers = [];
var deliveryAddresses = [];

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
    anchor: new google.maps.Point(16, 16),
    scale: 0.4,
    strokeWeight: 0,
    strokeColor: '#000',
    strokeOpacity: 1,
    fillColor: iconFillColor,
    fillOpacity: 1,
  }
}

function createMarker(position, iconPath, iconFillColor) {
  return new google.maps.Marker({
    position: position,
    map: map,
    animation: google.maps.Animation.DROP,
    icon: createMarkerIcon(iconPath, iconFillColor),
  });
}

function animate(time) {
  requestAnimationFrame(animate);
  TWEEN.update(time);
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

      requestAnimationFrame(animate);
    }

    marker = {
      key: key,
      courier: order.courier,
      color: randomColor,
      restaurantMarker: createMarker(order.restaurant, MarkerIcons.CUTLERY, randomColor),
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