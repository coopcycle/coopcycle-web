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

var storeSearch = document.querySelector('#store-search'), originAddressComponent;

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
      onLocationChange({
        latitude: store.address.latitude,
        longitude: store.address.longitude
      }, 'origin', 'cube', '#E74C3C')
      $('#originAddressChecked').removeClass('hidden')
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


// for non-admin disable submit until the price has been calculated
if (!window.AppData.isAdmin) {
  $('#delivery-submit').attr('disabled', true)
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
  $('#delivery_price').attr('disabled', true)
  $('#delivery-submit').attr('disabled', true)

  $.getJSON(window.AppData.DeliveryForm.calculatePriceURL, deliveryParams)
    .then(price => {

      $('#delivery-submit').attr('disabled', false)
      $('#delivery_price').attr('disabled', false)

      // we couldn't calculate the price
      if (isNaN(price)) {
        $('#no-price-warning').show()
        $('#delivery_price').val('')
        $('#delivery_price').focus()
      }
      else {
        $('#delivery_price').val(numeral(price).format('0,0.00'))
      }
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

map = MapHelper.init('map');

const date = $('#delivery_date').val();
const error = $('#datetimepicker').data('has-error');

render(
  <DateTimePicker
    error={error}
    onChange={onDateTimeChange}
    defaultValue={date ? moment(date) : moment() } />,
  document.getElementById('datetimepicker')
);
