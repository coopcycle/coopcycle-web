var MapHelper = require('../MapHelper');
import _ from 'underscore';
import React from 'react';
import { render } from 'react-dom';
import moment from 'moment';

import DateTimePicker from './DateTimePicker.jsx';

var center = {
  lat: 48.857498,
  lng: 2.335402
};
var zoom = window.mapZoom || 13;

var map;
var originMarker;
var deliveryMarker;

var autocompleteOptions = {
  types: ['address'],
  componentRestrictions: {
    country: "fr"
  }
};

function refreshRouting() {
  var params = {
    origin: [originMarker.getLatLng().lat, originMarker.getLatLng().lng].join(','),
    destination: [deliveryMarker.getLatLng().lat, deliveryMarker.getLatLng().lng].join(',')
  }

  fetch('/api/routing/route?origin=' + params.origin + '&destination=' + params.destination)
    .then((response) => {
      response.json().then((data) => {

        var duration = parseInt(data.routes[0].duration, 10);
        var distance = parseInt(data.routes[0].distance, 10);

        var kms = (distance / 1000).toFixed(2);
        var minutes = Math.ceil(duration / 60);

        $('#delivery_distance').text(kms + ' Km');
        $('#delivery_duration').text(minutes + ' min');

        // return decodePolyline(data.routes[0].geometry);
      })
    });
}

function autocompleteInput(prefix, type) {
  var input = document.getElementById(prefix + '_streetAddress');
  var autocomplete = new google.maps.places.Autocomplete(input, autocompleteOptions);
  autocomplete.addListener('place_changed', function() {

    var place = autocomplete.getPlace();

    if (!place.geometry) {
      console.log("Autocomplete's returned place contains no geometry");
      return;
    }

    $('#' + prefix + '_latitude').val(place.geometry.location.lat());
    $('#' + prefix + '_longitude').val(place.geometry.location.lng());

    var position = {
      lat: place.geometry.location.lat(),
      lng: place.geometry.location.lng(),
    }

    if (type === 'origin') {
      if (originMarker) {
        map.removeLayer(originMarker);
      }
      originMarker = MapHelper.createMarker(position, 'cube', 'marker', '#E74C3C');
      originMarker.addTo(map);
    }

    if (type === 'delivery') {
      if (deliveryMarker) {
        map.removeLayer(deliveryMarker);
      }
      deliveryMarker = MapHelper.createMarker(position, 'flag', 'marker', '#2ECC71');
      deliveryMarker.addTo(map);
    }

    if (originMarker && deliveryMarker) {
      refreshRouting();
    }

    var markers = _.filter([originMarker, deliveryMarker]);
    if (markers.length > 0) {
      MapHelper.fitToLayers(map, _.filter([originMarker, deliveryMarker]));
    }

  });
}

window.initMap = function() {

  autocompleteInput('delivery_originAddress', 'origin');
  autocompleteInput('delivery_deliveryAddress', 'delivery');

  const originLat = $('#delivery_originAddress_latitude').val();
  const originLng = $('#delivery_originAddress_longitude').val();
  if (originLat && originLng) {
    originMarker = MapHelper.createMarker({ lat: originLat, lng: originLng }, 'cube', 'marker', '#E74C3C');
    originMarker.addTo(map);
  }

  const deliveryLat = $('#delivery_deliveryAddress_latitude').val();
  const deliveryLng = $('#delivery_deliveryAddress_longitude').val();
  if (originLat && originLng) {
    deliveryMarker = MapHelper.createMarker({ lat: deliveryLat, lng: deliveryLng }, 'flag', 'marker', '#2ECC71');
    deliveryMarker.addTo(map);
  }

  if (originMarker && deliveryMarker) {
    refreshRouting();
  }

  var markers = _.filter([originMarker, deliveryMarker]);
  if (markers.length > 0) {
    MapHelper.fitToLayers(map, _.filter([originMarker, deliveryMarker]));
  }

}

function onDateTimeChange(date) {
  $('#delivery_date').val(date.format('YYYY-MM-DD HH:mm:00'))
}

map = MapHelper.init('map', center, zoom);

const date = $('#delivery_date').val();
const error = $('#datetimepicker').data('has-error');

render(<DateTimePicker error={error} onChange={onDateTimeChange} defaultValue={date ? moment(date) : moment() } />, document.getElementById('datetimepicker'));
