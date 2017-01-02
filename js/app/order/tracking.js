var MarkerIcons = require('fontawesome-markers');
var _ = require('underscore');
var TWEEN = require('tween.js');
var moment = require('moment');

var map;

var orders = [];
var couriers = [];

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

  return marker.marker;
}

function closeAllInfoWindows() {
  _.each(infoWindows, function(infoWindow) {
    infoWindow.close();
  });
}

var socket;

var order = window.__order;

var bounds;

moment.locale($('html').attr('lang'));

function updateTimestamps() {
  $('#order-events').find('[data-timestamp]').each((index, el) => {
    var timestamp = $(el).data('timestamp');
    $(el).find('.pull-right').text(moment.unix(timestamp).fromNow());
  });
}

setInterval(updateTimestamps, 30000);

updateTimestamps();

var centered = false;

window.initMap = function() {
  map = new google.maps.Map(document.getElementById('map'), {
    center: center,
    zoom: zoom
  });

  bounds = new google.maps.LatLngBounds();

  var hostname = window.location.hostname;

  setTimeout(function() {

    socket = io('//' + hostname, {path: '/order-tracking/socket.io'});

    socket.on('connect', function() {
      socket.emit('order', window.__order);
    });

    var restaurant = {
      lat: window.__order.restaurant.geo.latitude,
      lng: window.__order.restaurant.geo.longitude,
    }
    var deliveryAddress = {
      lat: window.__order.deliveryAddress.geo.latitude,
      lng: window.__order.deliveryAddress.geo.longitude,
    }

    var restaurantMarker = createMarker(restaurant, MarkerIcons.CUTLERY, COLORS.NEPHRITIS);
    var deliveryAddressMarker = createMarker(deliveryAddress, MarkerIcons.MAP_MARKER, COLORS.NEPHRITIS);

    bounds.extend(restaurantMarker.position);
    bounds.extend(deliveryAddressMarker.position);

    map.setCenter(bounds.getCenter());
    map.fitBounds(bounds);

    socket.emit('order', window.__order);

    socket.on('courier', function (data) {
      var marker = addCourier(data.key, data.coords);
      if (!centered) {
        bounds.extend(marker.position);
        map.setCenter(bounds.getCenter());
        map.fitBounds(bounds);
        centered = true;
      }
    });

    socket.on('order_event', function (data) {
      var $span = $('<span>')
        .addClass('pull-right')
        .text(moment.unix(data.timestamp).fromNow());

      var $alert = $('<div>')
        .addClass('alert alert-success')
        .attr('data-timestamp', data.timestamp)
        .text(data.status)
        .append($span);

      $('#order-events').append($alert);
    });
  }, 1000);
}