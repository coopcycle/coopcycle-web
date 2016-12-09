var MarkerIcons = require('fontawesome-markers');
var _ = require('underscore');

var map;

var orders = [];
var couriers = [];
var deliveryAddresses = [];

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

function addOrder(order) {

  var key = order.key;
  var position = order.coords;
  var color;

  switch (order.state) {
    default:
    case 'WAITING':
      color = '#27ae60';
      break;
    case 'DISPATCHING':
      color = '#2980b9';
      break;
    case 'DELIVERING':
      color = '#e74c3c';
      break;
  }

  var marker = _.find(orders, function(marker) {
    return marker.key === key;
  });

  if (!marker) {
    marker = {
      key: key,
      marker: createMarker(position, MarkerIcons.CUTLERY, color)
    };
    //   var circle = new google.maps.Circle({
    //     strokeColor: '#E74C3C',
    //     strokeOpacity: 0.8,
    //     strokeWeight: 1,
    //     fillColor: '#E74C3C',
    //     fillOpacity: 0.35,
    //     map: map,
    //     center: position,
    //     radius: 1500
    //   });
    orders.push(marker);
  } else {
    marker.marker.setIcon(createMarkerIcon(MarkerIcons.CUTLERY, color));
  }
}

function addDeliveryAddress(deliveryAddress) {

  var key = deliveryAddress.key;
  var position = deliveryAddress.coords;
  var color = '#8E44AD';

  var marker = _.find(deliveryAddresses, function(marker) {
    return marker.key === key;
  });

  if (!marker) {
    marker = {
      key: key,
      marker: createMarker(position, MarkerIcons.MAP_MARKER, color)
    };
    deliveryAddresses.push(marker);
  } else {
    // marker.marker.setIcon(createMarkerIcon(MarkerIcons.CUTLERY, color));
  }
}

function addCourier(key, position) {

  var marker = _.find(couriers, function(marker) {
    return marker.key === key;
  });

  if (!marker) {

    var infoWindow = new google.maps.InfoWindow({
      content: key
    });
    infoWindows.push(infoWindow);

    marker = {
      key: key,
      marker: createMarker(position, MarkerIcons.BICYCLE, '#27ae60'),
    };

    marker.marker.addListener('click', function() {
      closeAllInfoWindows();
      infoWindow.open(map, marker.marker);
    });

    couriers.push(marker);
  } else {
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
      marker[0].marker.setMap(null);
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

    socket.on('delivery_addresses', function (data) {
      console.log(data);
      removeMissingObjects(data, deliveryAddresses);
      for (var i = 0; i < data.length; i++) {
        addDeliveryAddress(data[i]);
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