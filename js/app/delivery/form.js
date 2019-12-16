import MapHelper from '../MapHelper'
import _ from 'lodash'
require('gasparesganga-jquery-loading-overlay')

import DeliveryForm from '../forms/delivery'
import PricePreview from './PricePreview'

let map
let form
let pricePreview

let markers = {
  pickup: null,
  dropoff: null,
}

function route(delivery) {

  const { pickup, dropoff } = delivery

  if (!pickup.address || !dropoff.address) {
    return Promise.reject('Missing pickup.address and/or dropoff.address')
  }

  return MapHelper.route([
    [ pickup.address.geo.latitude, pickup.address.geo.longitude ],
    [ dropoff.address.geo.latitude, dropoff.address.geo.longitude ]
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

  if (!map) {
    return
  }

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

function serializeAddress(address) {
  if (address.hasOwnProperty('@id')) {
    return address['@id']
  }

  return {
    streetAddress: address.streetAddress,
    latLng: [
      address.geo.latitude,
      address.geo.longitude
    ]
  }
}

window.initMap = function() {

  form = new DeliveryForm('delivery', {
    onReady: function(delivery) {
      if (delivery.pickup.address) {
        createMarker({
          latitude: delivery.pickup.address.geo.latitude,
          longitude: delivery.pickup.address.geo.longitude
        }, 'pickup')
        markAddressChecked('pickup')
      }
      if (delivery.dropoff.address) {
        createMarker({
          latitude: delivery.dropoff.address.geo.latitude,
          longitude: delivery.dropoff.address.geo.longitude
        }, 'dropoff')
        markAddressChecked('dropoff')
      }
    },
    onChange: function(delivery) {

      if (delivery.pickup.address) {
        createMarker({
          latitude: delivery.pickup.address.geo.latitude,
          longitude: delivery.pickup.address.geo.longitude
        }, 'pickup')
        markAddressChecked('pickup')
        $('#delivery_pickup_panel_title').text(delivery.pickup.address.streetAddress)
      }
      if (delivery.dropoff.address) {
        createMarker({
          latitude: delivery.dropoff.address.geo.latitude,
          longitude: delivery.dropoff.address.geo.longitude
        }, 'dropoff')
        markAddressChecked('dropoff')
        $('#delivery_dropoff_panel_title').text(delivery.dropoff.address.streetAddress)
      }

      if (delivery.pickup.address && delivery.dropoff.address) {

        this.disable()

        route(delivery)
          .then((infos) => {

            $('#delivery_distance').text(`${infos.kms} Km`)
            $('#delivery_duration').text(`${infos.minutes} min`)

            if (delivery.store && pricePreview) {

              const deliveryAsPayload = {
                ...delivery,
                pickup: {
                  ...delivery.pickup,
                  address: serializeAddress(delivery.pickup.address)
                },
                dropoff: {
                  ...delivery.dropoff,
                  address: serializeAddress(delivery.dropoff.address)
                }
              }

              pricePreview.update(deliveryAsPayload)
            }

            form.enable()
          })
          // eslint-disable-next-line no-console
          .catch(e => console.error(e))
      }
    }
  })

}

const priceEl = document.getElementById('delivery-price')

if (priceEl) {
  $('form[name="delivery"]').LoadingOverlay('show', {
    image: false,
  })
  $.getJSON(window.Routing.generate('profile_jwt'))
    .then(token => {
      $('form[name="delivery"]').LoadingOverlay('hide')
      pricePreview = new PricePreview(priceEl, { token })
    })
}

if (document.getElementById('map')) {
  map = MapHelper.init('map')
}
