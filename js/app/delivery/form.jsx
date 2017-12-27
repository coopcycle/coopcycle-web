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

function calculatePrice(distance, delivery) {

  $('#delivery_price').attr('disabled', true)

  const deliveryParams = {
    distance,
    delivery_address: [ delivery.getLatLng().lat, delivery.getLatLng().lng ].join(','),
    pricing_rule_set: $('#delivery_pricingRuleSet').val(),
    vehicle: $('#delivery_vehicle').val(),
  }

  $.getJSON(window.AppData.DeliveryForm.calculatePriceURL, deliveryParams)
    .then(price => {
      $('#delivery_price').val(numeral(price).format('0,0.00'))
      $('#delivery_price').attr('disabled', false)
    })
    .catch(e => {
      $('#delivery_price').attr('disabled', false)
    })
}

// Update price when parameters have changed
if ($('#delivery_pricingRuleSet').is('select')) {
  $('#delivery_pricingRuleSet').on('change', function(e) {
    if (_.filter(markers).length === 2) {
      refreshRouting()
    }
  })
}
$('#delivery_vehicle').on('change', function(e) {
  if (_.filter(markers).length === 2) {
    refreshRouting()
  }
})

function refreshRouting() {

  const { origin, delivery } = markers

  MapHelper.route([
    [ origin.getLatLng().lat, origin.getLatLng().lng ],
    [ delivery.getLatLng().lat, delivery.getLatLng().lng ]
  ]).then(route => {

    var duration = parseInt(route.duration, 10);
    var distance = parseInt(route.distance, 10);

    var kms = (distance / 1000).toFixed(2);
    var minutes = Math.ceil(duration / 60);

    $('#delivery_distance').text(kms + ' Km');
    $('#delivery_duration').text(minutes + ' min');

    if (window.AppData.DeliveryForm.calculatePriceURL) {
      calculatePrice(distance, delivery)
    }

    // return decodePolyline(data.routes[0].geometry);
  })
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

  const originAddressLatitude = document.querySelector('#delivery_originAddress_latitude')
  const originAddressLongitude = document.querySelector('#delivery_originAddress_longitude')

  const hasOriginAddress = originAddressLatitude.value && originAddressLongitude.value

  if (hasOriginAddress) {
    onLocationChange({
      latitude: originAddressLatitude.value,
      longitude: originAddressLongitude.value
    }, 'origin', 'cube', '#E74C3C')
    $('#originAddressChecked').removeClass('hidden')
    setTimeout(() => $('#collapseOriginAddress').collapse('hide'), 500)
  }

  new CoopCycle.AddressInput(document.querySelector('#delivery_originAddress_streetAddress'), {
    elements: {
      latitude: document.querySelector('#delivery_originAddress_latitude'),
      longitude: document.querySelector('#delivery_originAddress_longitude'),
      postalCode: document.querySelector('#delivery_originAddress_postalCode'),
      addressLocality: document.querySelector('#delivery_originAddress_addressLocality')
    },
    onLocationChange: location => onLocationChange(location, 'origin', 'cube', '#E74C3C'),
    onAddressChange: address => {
      $('#originAddressTitleLabel').text(address.streetAddress)
      $('#originAddressChecked').removeClass('hidden')
      if (!hasOriginAddress) {
        setTimeout(() => $('#collapseOriginAddress').collapse('hide'), 500)
        setTimeout(() => $('#delivery_deliveryAddress_streetAddress').focus(), 500)
      }
    }
  })
  new CoopCycle.AddressInput(document.querySelector('#delivery_deliveryAddress_streetAddress'), {
    elements: {
      latitude: document.querySelector('#delivery_deliveryAddress_latitude'),
      longitude: document.querySelector('#delivery_deliveryAddress_longitude'),
      postalCode: document.querySelector('#delivery_deliveryAddress_postalCode'),
      addressLocality: document.querySelector('#delivery_deliveryAddress_addressLocality')
    },
    onLocationChange: location => onLocationChange(location, 'delivery', 'flag', '#2ECC71'),
    onAddressChange: address => {
      $('#deliveryAddressTitleLabel').text(address.streetAddress)
      $('#deliveryAddressChecked').removeClass('hidden')
    },
    onLoad: () => {
      if (hasOriginAddress) {
        setTimeout(() => $('#delivery_deliveryAddress_streetAddress').focus(), 500)
      }
    }
  })
}

function onDateTimeChange(date) {
  $('#delivery_date').val(date.format('YYYY-MM-DD HH:mm:00'))
}

map = MapHelper.init('map', center, zoom);

const date = $('#delivery_date').val();
const error = $('#datetimepicker').data('has-error');

render(<DateTimePicker error={error} onChange={onDateTimeChange} defaultValue={date ? moment(date) : moment() } />, document.getElementById('datetimepicker'));
