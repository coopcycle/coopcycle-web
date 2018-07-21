import MapHelper from '../MapHelper';
import _ from 'lodash';
import React from 'react';
import { render } from 'react-dom';
import moment from 'moment';

var map;

let markers = {
  pickup: null,
  dropoff: null,
}

function disableForm() {
  $('#delivery-submit').attr('disabled', true)
  $('#loader').removeClass('hidden')
}

function enableForm() {
  $('#delivery-submit').attr('disabled', false)
  $('#loader').addClass('hidden')
}

function calculatePrice(distance, pickup, dropoff) {

  const deliveryParams = {
    distance,
    pickup_address: [ pickup.getLatLng().lat, pickup.getLatLng().lng ].join(','),
    dropoff_address: [ dropoff.getLatLng().lat, dropoff.getLatLng().lng ].join(','),
    pricing_rule_set: $('#delivery_pricingRuleSet').val(),
    vehicle: $('#delivery_vehicle').val(),
    weight: $('#delivery_weight').val()
  }

  $('#no-price-warning').hide()
  disableForm()

  $.getJSON(window.AppData.DeliveryForm.calculatePriceURL, deliveryParams)
    .then(price => {
      if (isNaN(price)) {
        $('#no-price-warning').show()
        $('#delivery_price').text('')
      } else {
        $('#delivery_price').text((price / 100).formatMoney(2, window.AppData.currencySymbol))
      }
      enableForm()
    })
    .catch(e => enableForm())
}

function refreshRouting() {

  // We need to have 2 markers
  if (_.filter(markers).length < 2) return

  const { pickup, dropoff } = markers

  disableForm()

  MapHelper.route([
    [ pickup.getLatLng().lat, pickup.getLatLng().lng ],
    [ dropoff.getLatLng().lat, dropoff.getLatLng().lng ]
  ])
  .then(route => {

    var duration = parseInt(route.duration, 10);
    var distance = parseInt(route.distance, 10);

    var kms = (distance / 1000).toFixed(2);
    var minutes = Math.ceil(duration / 60);

    $('#delivery_distance').text(kms + ' Km');
    $('#delivery_duration').text(minutes + ' min');

    calculatePrice(distance, pickup, dropoff)

    // return decodePolyline(data.routes[0].geometry);
  })
  .catch(e => enableForm())
}

const markerIcons = {
  pickup:  { icon: 'cube', color: '#E74C3C' },
  dropoff: { icon: 'flag', color: '#2ECC71' }
}

const addressTypeSelector = {
  pickup:  { checkmark: '#delivery_pickup_checked' },
  dropoff: { checkmark: '#delivery_dropoff_checked' }
}

function createMarker(location, addressType) {
  const { icon, color } = markerIcons[addressType]
  if (markers[addressType]) {
    map.removeLayer(markers[addressType])
  }
  markers[addressType] = MapHelper.createMarker({
    lat: location.latitude,
    lng: location.longitude
  }, icon, 'marker', color)
  markers[addressType].addTo(map)

  MapHelper.fitToLayers(map, _.filter(markers))
}

function markAddressChecked(addressType) {
  const { checkmark } = addressTypeSelector[addressType]
  $(checkmark).removeClass('hidden')
}

function onLocationChange(location, addressType, markerIcon, markerColor) {
  createMarker(location, addressType)
  refreshRouting()
}

window.initMap = function() {

  const originAddressLatitude  = document.querySelector('#delivery_pickup_address_latitude')
  const originAddressLongitude = document.querySelector('#delivery_pickup_address_longitude')

  const deliveryAddressLatitude  = document.querySelector('#delivery_dropoff_address_latitude')
  const deliveryAddressLongitude = document.querySelector('#delivery_dropoff_address_longitude')

  const hasOriginAddress = originAddressLatitude.value && originAddressLongitude.value
  const hasDeliveryAddress = deliveryAddressLongitude.value && deliveryAddressLatitude.value

  if (hasOriginAddress) {
    markAddressChecked('pickup')
    createMarker({
      latitude: originAddressLatitude.value,
      longitude: originAddressLongitude.value
    }, 'pickup')
  }

  if (hasDeliveryAddress) {
    markAddressChecked('dropoff')
    createMarker({
      latitude: deliveryAddressLatitude.value,
      longitude: deliveryAddressLongitude.value
    }, 'dropoff')
  }

  new CoopCycle.AddressInput(document.querySelector('#delivery_pickup_address_streetAddress'), {
    elements: {
      latitude: document.querySelector('#delivery_pickup_address_latitude'),
      longitude: document.querySelector('#delivery_pickup_address_longitude'),
      postalCode: document.querySelector('#delivery_pickup_address_postalCode'),
      addressLocality: document.querySelector('#delivery_pickup_address_addressLocality')
    },
    onLocationChange: location => onLocationChange(location, 'pickup'),
    onAddressChange: address => {
      $('#delivery_pickup_panel_title').text(address.streetAddress)
      markAddressChecked('pickup')
      if (!hasOriginAddress) {
        setTimeout(() => $('#delivery_pickup_collapse').collapse('hide'), 500)
        setTimeout(() => $('#delivery_pickup_address_streetAddress').focus(), 500)
      }
    }
  })

  new CoopCycle.DateTimePicker(document.querySelector('#delivery_pickup_doneBefore_widget'), {
    defaultValue: document.querySelector('#delivery_pickup_doneBefore').value,
    onChange: function(date, dateString) {
      document.querySelector('#delivery_pickup_doneBefore').value = date.format('YYYY-MM-DD HH:mm:ss')
    }
  })

  new CoopCycle.AddressInput(document.querySelector('#delivery_dropoff_address_streetAddress'), {
    elements: {
      latitude: document.querySelector('#delivery_dropoff_address_latitude'),
      longitude: document.querySelector('#delivery_dropoff_address_longitude'),
      postalCode: document.querySelector('#delivery_dropoff_address_postalCode'),
      addressLocality: document.querySelector('#delivery_dropoff_address_addressLocality')
    },
    onLocationChange: location => onLocationChange(location, 'dropoff'),
    onAddressChange: address => {
      $('#delivery_dropoff_panel_title').text(address.streetAddress)
      markAddressChecked('dropoff')
    }
  })

  new CoopCycle.DateTimePicker(document.querySelector('#delivery_dropoff_doneBefore_widget'), {
    defaultValue: document.querySelector('#delivery_dropoff_doneBefore').value,
    onChange: function(date, dateString) {
      document.querySelector('#delivery_dropoff_doneBefore').value = date.format('YYYY-MM-DD HH:mm:ss')
    }
  })
}

map = MapHelper.init('map');

// Update price when parameters have changed
if ($('#delivery_pricingRuleSet').is('select')) {
  $('#delivery_pricingRuleSet').on('change', e => refreshRouting())
}
$('#delivery_vehicle').on('change', e => refreshRouting())
