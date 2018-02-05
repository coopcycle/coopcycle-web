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

var map;

let markers = {
  origin: null,
  delivery: null,
}

const storeSearch = document.querySelector('#store-search')

if (storeSearch) {

  var options = {
    url: window.AppData.adminStoreSearchUrl,
    placeholder: "",
    clearOnSelect: true,
    onSuggestionSelected: function(store) {

      $('#delivery_store').val(store.id)
      $('#delivery_pricingRuleSet').val(store.pricingRuleSetId)

      document.querySelector('#delivery_originAddress_streetAddress').value = store.address.streetAddress
      document.querySelector('#delivery_originAddress_latitude').value = store.address.latitude
      document.querySelector('#delivery_originAddress_longitude').value = store.address.longitude
      document.querySelector('#delivery_originAddress_postalCode').value = store.address.postalCode
      document.querySelector('#delivery_originAddress_addressLocality').value = store.address.addressLocality

      $('#selected-store-name').text(store.name)
      $('#selected-store').removeClass('hidden')

      // refresh the map on the right
      onLocationChange(store.address, 'origin')
      markAddressChecked('origin')
      setTimeout(() => $('#collapseOriginAddress').collapse('hide'), 500)
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

function calculatePrice(distance, delivery) {

  const deliveryParams = {
    distance,
    delivery_address: [ delivery.getLatLng().lat, delivery.getLatLng().lng ].join(','),
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

  const { origin, delivery } = markers

  MapHelper.route([
    [ origin.getLatLng().lat, origin.getLatLng().lng ],
    [ delivery.getLatLng().lat, delivery.getLatLng().lng ]
  ]).then(route => {

    var duration = parseInt(route.duration, 10);
    var distance = parseInt(route.distance, 10);

    var kms = (distance / 1000).toFixed(2);
    var minutes = Math.ceil(duration / 60);

    $('#delivery_distance--display').text(kms + ' Km');
    $('#delivery_distance').val(distance);
    $('#delivery_duration--display').text(minutes + ' min');
    $('#delivery_duration').val(duration);

    if (window.AppData.DeliveryForm.calculatePriceURL) {
      calculatePrice(distance, delivery)
    }

    // return decodePolyline(data.routes[0].geometry);
  })
}

const markerIcons = {
  origin:   { icon: 'cube', color: '#E74C3C' },
  delivery: { icon: 'flag', color: '#2ECC71' }
}

const addressTypeSelector = {
  origin:   { checkmark: '#originAddressChecked' },
  delivery: { checkmark: '#deliveryAddressChecked' }
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

  const originAddressLatitude  = document.querySelector('#delivery_originAddress_latitude')
  const originAddressLongitude = document.querySelector('#delivery_originAddress_longitude')

  const deliveryAddressLatitude  = document.querySelector('#delivery_deliveryAddress_latitude')
  const deliveryAddressLongitude = document.querySelector('#delivery_deliveryAddress_longitude')

  const hasOriginAddress = originAddressLatitude.value && originAddressLongitude.value
  const hasDeliveryAddress = deliveryAddressLongitude.value && deliveryAddressLatitude.value

  if (hasOriginAddress) {
    markAddressChecked('origin')
    createMarker({
      latitude: originAddressLatitude.value,
      longitude: originAddressLongitude.value
    }, 'origin')
    $('#collapseOriginAddress').collapse('hide')
  }

  if (hasDeliveryAddress) {
    markAddressChecked('delivery')
    createMarker({
      latitude: deliveryAddressLatitude.value,
      longitude: deliveryAddressLongitude.value
    }, 'delivery')
    $('#collapseDeliveryAddress').collapse('hide')
  }

  new CoopCycle.AddressInput(document.querySelector('#delivery_originAddress_streetAddress'), {
    elements: {
      latitude: document.querySelector('#delivery_originAddress_latitude'),
      longitude: document.querySelector('#delivery_originAddress_longitude'),
      postalCode: document.querySelector('#delivery_originAddress_postalCode'),
      addressLocality: document.querySelector('#delivery_originAddress_addressLocality')
    },
    onLocationChange: location => onLocationChange(location, 'origin'),
    onAddressChange: address => {
      $('#originAddressTitleLabel').text(address.streetAddress)
      markAddressChecked('origin')
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
    onLocationChange: location => onLocationChange(location, 'delivery'),
    onAddressChange: address => {
      $('#deliveryAddressTitleLabel').text(address.streetAddress)
      markAddressChecked('delivery')
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

map = MapHelper.init('map');

const date = $('#delivery_date').val();
const error = $('#datetimepicker').data('has-error');

// For non-admin disable submit until the price has been calculated
if (!window.AppData.isAdmin) {
  $('#delivery-submit').attr('disabled', true)
}

// Update price when parameters have changed
if ($('#delivery_pricingRuleSet').is('select')) {
  $('#delivery_pricingRuleSet').on('change', e => refreshRouting())
}
$('#delivery_vehicle').on('change', e => refreshRouting())

render(
  <DateTimePicker
    error={error}
    onChange={onDateTimeChange}
    defaultValue={date ? moment(date) : moment() } />,
  document.getElementById('datetimepicker')
);
