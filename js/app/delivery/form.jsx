import MapHelper from '../MapHelper'
import _ from 'lodash'
import moment from 'moment'
require('gasparesganga-jquery-loading-overlay')

import DeliveryForm from '../forms/delivery'
import PricePreview from './PricePreview'

let map
let jwt
let form
let pricePreview

let markers = {
  pickup: null,
  dropoff: null,
}

function route() {

  // We need to have 2 markers
  if (_.filter(markers).length < 2) return

  const { pickup, dropoff } = markers

  return MapHelper.route([
    [ pickup.getLatLng().lat, pickup.getLatLng().lng ],
    [ dropoff.getLatLng().lat, dropoff.getLatLng().lng ]
  ])
    .then(route => {

      var duration = parseInt(route.duration, 10)
      var distance = parseInt(route.distance, 10)

      var kms = (distance / 1000).toFixed(2)
      var minutes = Math.ceil(duration / 60)

      return {
        duration,
        distance,
        kms,
        minutes
      }
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

  form = new DeliveryForm('delivery', {
    onChange: function(delivery) {

      const { pickup, dropoff } = delivery

      if (pickup.address.latLng) {
        createMarker({
          latitude: pickup.address.latLng[0],
          longitude: pickup.address.latLng[1]
        }, 'pickup')
        markAddressChecked('pickup')
      }
      if (pickup.address.streetAddress) {
        $('#delivery_pickup_panel_title').text(pickup.address.streetAddress)
      }

      if (dropoff.address.latLng) {
        createMarker({
          latitude: dropoff.address.latLng[0],
          longitude: dropoff.address.latLng[1]
        }, 'dropoff')
        markAddressChecked('dropoff')
      }
      if (dropoff.address.streetAddress) {
        $('#delivery_dropoff_panel_title').text(dropoff.address.streetAddress)
      }

      if (pickup.address.latLng && dropoff.address.latLng) {

        this.disable()

        route()
          .then((infos) => {

            $('#delivery_distance').text(`${infos.kms} Km`)
            $('#delivery_duration').text(`${infos.minutes} min`)

            if (delivery.store && pricePreview) {
              pricePreview.update(delivery)
            }

            form.enable()
          })
      }
    }
  })

}

$.getJSON(window.Routing.generate('profile_jwt'))
  .then(tok => {
    $('form[name="delivery"]').LoadingOverlay('hide')
    jwt = tok
    let el = document.getElementById('delivery-price')
    if (el) {
      pricePreview = new PricePreview(document.getElementById('delivery-price'), {
        token: tok
      })
    }
  })

map = MapHelper.init('map')

$('form[name="delivery"]').LoadingOverlay('show', {
  image: false,
})
