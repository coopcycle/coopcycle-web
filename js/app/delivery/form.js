import MapHelper from '../MapHelper'
import _ from 'lodash'
require('gasparesganga-jquery-loading-overlay')

import DeliveryForm from '../forms/delivery'
import PricePreview from './PricePreview'

import './form.scss'

let map
let form
let pricePreview

let markers = {
  pickup: null,
  dropoff: null,
}

function route(delivery) {

  if (!isValid(delivery)) {
    return Promise.reject('Missing pickup.address and/or dropoff.address')
  }

  const coords = delivery.tasks.map(t => ([
    t.address.geo.latitude,
    t.address.geo.longitude
  ]))

  return MapHelper.route(coords)
    .then(route => {

      var distance = parseInt(route.distance, 10)
      var kms = (distance / 1000).toFixed(2)

      return {
        distance,
        kms,
      }
    })
}

const markerIcons = {
  pickup:  { icon: 'cube', color: '#E74C3C' },
  dropoff: { icon: 'flag', color: '#2ECC71' }
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

function removeMarker(addressType) {

  if (!map) {
    return
  }

  const marker = markers[addressType]

  if (!marker) {
    return
  }

  marker.removeFrom(map)

  MapHelper.fitToLayers(map, _.filter(markers))
}

function serializeAddress(address) {
  if (Object.prototype.hasOwnProperty.call(address, '@id')) {
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

function isValid(delivery) {
  const tasksWithoutAddress = _.filter(delivery.tasks, t => _.isEmpty(t.address))

  return tasksWithoutAddress.length === 0
}

if (document.getElementById('map')) {
  map = MapHelper.init('map')
}

form = new DeliveryForm('delivery', {
  onReady: function(delivery) {
    delivery.tasks.forEach(task => {
      if (task.address) {
        createMarker({
          latitude: task.address.geo.latitude,
          longitude: task.address.geo.longitude
        }, task.type.toLowerCase())
      }
    })
  },
  onChange: function(delivery) {

    delivery.tasks.forEach(task => {
      if (task.address) {
        createMarker({
          latitude: task.address.geo.latitude,
          longitude: task.address.geo.longitude
        }, task.type.toLowerCase())
      } else {
        removeMarker(task.type.toLowerCase())
      }
    })

    if (isValid(delivery)) {

      this.disable()

      const updateDistance = new Promise((resolve) => {
        route(delivery).then((infos) => {
          $('#delivery_distance').text(`${infos.kms} Km`)
          resolve()
        })
      })

      const updatePrice = new Promise((resolve) => {
        if (delivery.store && pricePreview) {

          const tasks = delivery.tasks.slice(0)

          const deliveryAsPayload = {
            ...delivery,
            tasks: tasks.map(t => ({
              ...t,
              address: serializeAddress(t.address)
            }))
          }

          pricePreview.update(deliveryAsPayload).then(() => resolve())
        } else {
          resolve()
        }
      })

      Promise.all([
        updateDistance,
        updatePrice,
      ])
      .then(() => {
        form.enable()
      })
      // eslint-disable-next-line no-console
      .catch(e => console.error(e))
    }
  }
})

const priceEl = document.getElementById('delivery-price')

if (priceEl) {
  $('form[name="delivery"]').LoadingOverlay('show', {
    image: false,
  })
  $.getJSON(window.Routing.generate('profile_jwt'))
    .then(result => {
      $('form[name="delivery"]').LoadingOverlay('hide')
      pricePreview = new PricePreview(priceEl, { token: result.jwt })
    })
}
