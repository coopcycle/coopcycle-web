var MapHelper = require('../MapHelper');
import _ from 'underscore';
import React from 'react';
import { render } from 'react-dom';
import moment from 'moment';
import numeral  from 'numeral';
import 'numeral/locales'
import DateTimePicker from './DateTimePicker.jsx';

const locale = $('html').attr('lang')
numeral.locale(locale)

var center = {
  lat: 48.857498,
  lng: 2.335402
};
var zoom = window.mapZoom || 13;

var map;

let markers = {
  origin: null,
  delivery: null,
}

function refreshRouting() {

  const { origin, delivery } = markers

  const params = {
    origin: [ origin.getLatLng().lat, origin.getLatLng().lng ].join(','),
    destination: [ delivery.getLatLng().lat, delivery.getLatLng().lng ].join(',')
  };

  fetch('/api/routing/route?' + $.param(params))
    .then(response => {
      response.json().then(data => {

        var duration = parseInt(data.routes[0].duration, 10);
        var distance = parseInt(data.routes[0].distance, 10);

        var kms = (distance / 1000).toFixed(2);
        var minutes = Math.ceil(duration / 60);

        $('#delivery_distance').text(kms + ' Km');
        $('#delivery_duration').text(minutes + ' min');

        $.getJSON(window.__deliveries_pricing_calculate_url, { distance })
          .then(price => {
            $('#delivery_price').val(numeral(price).format('0,0.00'))
          })

        // return decodePolyline(data.routes[0].geometry);
      })
    });
}

function onLocationChange(location, markerKey, markerIcon, markerColor) {

  const position = {
    lat: location.latitude,
    lng: location.longitude,
  }

  if (markers[markerKey]) {
    map.removeLayer(markers[markerKey])
  }
  markers[markerKey] = MapHelper.createMarker(position, markerIcon, 'marker', markerColor)
  markers[markerKey].addTo(map)

  const existingMarkers = _.filter(markers)

  if (existingMarkers.length === 2) {
    refreshRouting()
  }

  if (existingMarkers.length > 0) {
    MapHelper.fitToLayers(map, existingMarkers)
  }
}

window.initMap = function() {
  new CoopCycle.AddressInput(document.querySelector('#delivery_originAddress_streetAddress'), {
    elements: {
      latitude: document.querySelector('#delivery_originAddress_latitude'),
      longitude: document.querySelector('#delivery_originAddress_longitude'),
      postalCode: document.querySelector('#delivery_originAddress_postalCode'),
      addressLocality: document.querySelector('#delivery_originAddress_addressLocality')
    },
    onLocationChange: location => onLocationChange(location, 'origin', 'cube', '#E74C3C')
  })
  new CoopCycle.AddressInput(document.querySelector('#delivery_deliveryAddress_streetAddress'), {
    elements: {
      latitude: document.querySelector('#delivery_deliveryAddress_latitude'),
      longitude: document.querySelector('#delivery_deliveryAddress_longitude'),
      postalCode: document.querySelector('#delivery_deliveryAddress_postalCode'),
      addressLocality: document.querySelector('#delivery_deliveryAddress_addressLocality')
    },
    onLocationChange: location => onLocationChange(location, 'delivery', 'flag', '#2ECC71')
  })
}

function onDateTimeChange(date) {
  $('#delivery_date').val(date.format('YYYY-MM-DD HH:mm:00'))
}

map = MapHelper.init('map', center, zoom);

const date = $('#delivery_date').val();
const error = $('#datetimepicker').data('has-error');

render(<DateTimePicker error={error} onChange={onDateTimeChange} defaultValue={date ? moment(date) : moment() } />, document.getElementById('datetimepicker'));
