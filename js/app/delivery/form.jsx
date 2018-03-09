import MapHelper from '../MapHelper';
import _ from 'underscore';
import React from 'react';
import { render } from 'react-dom';
import moment from 'moment';
import numeral  from 'numeral';
import 'numeral/locales'

const locale = $('html').attr('lang')
numeral.locale(locale)

var map;

let markers = {
  pickup: null,
  dropoff: null,
}

const storeSearch = document.querySelector('#store-search')

if (storeSearch) {

  var options = {
    url: window.AppData.adminStoreSearchUrl,
    placeholder: document.querySelector('#delivery_store').getAttribute('placeholder'),
    clearOnSelect: true,
    onSuggestionSelected: function(store) {

      $('#delivery_store').val(store.id)
      $('#delivery_pricingRuleSet').val(store.pricingRuleSetId)

      document.querySelector('#delivery_pickup_address_streetAddress').value = store.address.streetAddress
      document.querySelector('#delivery_pickup_address_latitude').value = store.address.latitude
      document.querySelector('#delivery_pickup_address_longitude').value = store.address.longitude
      document.querySelector('#delivery_pickup_address_postalCode').value = store.address.postalCode
      document.querySelector('#delivery_pickup_address_addressLocality').value = store.address.addressLocality

      $('#selected-store-name').text(store.name)
      $('#selected-store').removeClass('hidden')

      // refresh the map on the right
      onLocationChange(store.address, 'pickup')
      markAddressChecked('pickup')
      setTimeout(() => $('#delivery_pickup_collapse').collapse('hide'), 500)
    }
  }

  new CoopCycle.Search(storeSearch, options);

  $('#selected-store .alert .close').on('click', function (e) {
    e.preventDefault()
    $('#delivery_store').val('')
    $('#delivery_pricingRuleSet').val('')
    $('#selected-store').addClass('hidden')
  })

}

function disableForm() {
  $('#delivery_price').attr('disabled', true)
  $('#delivery-submit').attr('disabled', true)
}

function enableForm() {
  $('#delivery_price').attr('disabled', false)
  $('#delivery-submit').attr('disabled', false)
}

function calculatePrice(distance, dropoff) {

  const deliveryParams = {
    distance,
    delivery_address: [ dropoff.getLatLng().lat, dropoff.getLatLng().lng ].join(','),
    pricing_rule_set: $('#delivery_pricingRuleSet').val(),
    vehicle: $('#delivery_vehicle').val(),
    weight: $('#delivery_weight').val()
  }

  $('#no-price-warning').hide()
  disableForm()

  $.getJSON(window.AppData.DeliveryForm.calculatePriceURL, deliveryParams)
    .then(price => {

      enableForm()

      // we couldn't calculate the price
      if (isNaN(price)) {
        $('#no-price-warning').show()
        if (!$('#delivery_price').val()) {
          $('#delivery_price').focus()
        }
      } else {
        $('#delivery_price').val(numeral(price).format('0,0.00'))
      }
    })
    .catch(e => enableForm())
}

function refreshRouting() {

  // We need to have 2 markers
  if (_.filter(markers).length < 2) return

  const { pickup, dropoff } = markers

  MapHelper.route([
    [ pickup.getLatLng().lat, pickup.getLatLng().lng ],
    [ dropoff.getLatLng().lat, dropoff.getLatLng().lng ]
  ]).then(route => {

    var duration = parseInt(route.duration, 10);
    var distance = parseInt(route.distance, 10);

    var kms = (distance / 1000).toFixed(2);
    var minutes = Math.ceil(duration / 60);

    $('#delivery_distance').text(kms + ' Km');
    $('#delivery_duration').text(minutes + ' min');

    if (window.AppData.DeliveryForm.calculatePriceURL) {
      calculatePrice(distance, dropoff)
    }

    // return decodePolyline(data.routes[0].geometry);
  })
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
    $('#delivery_pickup_collapse').collapse('hide')
  }

  if (hasDeliveryAddress) {
    markAddressChecked('dropoff')
    createMarker({
      latitude: deliveryAddressLatitude.value,
      longitude: deliveryAddressLongitude.value
    }, 'dropoff')
    $('#delivery_dropoff_collapse').collapse('hide')
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
    },
    onLoad: () => {
      if (hasOriginAddress) {
        setTimeout(() => $('#delivery_dropoff_address_streetAddress').focus(), 500)
      }
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

// For non-admin disable submit until the price has been calculated
if (!window.AppData.isAdmin) {
  $('#delivery-submit').attr('disabled', true)
}

// Update price when parameters have changed
if ($('#delivery_pricingRuleSet').is('select')) {
  $('#delivery_pricingRuleSet').on('change', e => refreshRouting())
}
$('#delivery_vehicle').on('change', e => refreshRouting())
