import MapHelper from '../MapHelper'
import L from 'leaflet'
import _ from 'lodash'
import JsBarcode from 'jsbarcode'
require('gasparesganga-jquery-loading-overlay')

import DeliveryForm from '../forms/delivery'
import PricePreview from './PricePreview'

import './form.scss'

let map
let polylineLayerGroup
let markersLayerGroup
let form
let pricePreview

JsBarcode('.barcode').init();

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
        polyline: MapHelper.decodePolyline(route.geometry),
      }
    })
}

const markerIcons = {
  pickup:  { icon: 'cube', color: '#E74C3C' },
  dropoff: { icon: 'flag', color: '#2ECC71' }
}

function createMarker(location, index, addressType) {

  if (!map) {
    return
  }

  const { icon, color } = markerIcons[addressType]

  const marker = MapHelper.createMarker({
    lat: location.latitude,
    lng: location.longitude
  }, icon, 'marker', color)

  marker.addTo(markersLayerGroup)
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
  polylineLayerGroup = new L.LayerGroup()
  polylineLayerGroup.addTo(map)

  markersLayerGroup = new L.LayerGroup()
  markersLayerGroup.addTo(map)
}

form = new DeliveryForm('delivery', {
  onReady: function(delivery) {
    delivery.tasks.forEach((task, index) => {
      if (task.address) {
        createMarker({
          latitude: task.address.geo.latitude,
          longitude: task.address.geo.longitude
        }, index, task.type.toLowerCase())
      }
    })
    MapHelper.fitToLayers(map, markersLayerGroup.getLayers())
  },
  onChange: function(delivery) {

    markersLayerGroup.clearLayers()
    delivery.tasks.forEach((task, index) => {
      if (task.address) {
        createMarker({
          latitude: task.address.geo.latitude,
          longitude: task.address.geo.longitude
        }, index, task.type.toLowerCase())
      }
    })
    MapHelper.fitToLayers(map, markersLayerGroup.getLayers())

    if (isValid(delivery)) {

      this.disable()
      polylineLayerGroup.clearLayers()

      const updateDistance = new Promise((resolve) => {
        route(delivery).then((infos) => {
          polylineLayerGroup.addLayer(
            MapHelper.createPolylineWithArrows(infos.polyline, '#3498DB')
          )
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

const arbitraryPriceEl = document.getElementById('delivery_arbitraryPrice')
const variantDetailsEl = document.querySelector('[data-variant-details]')

if (arbitraryPriceEl) {
  arbitraryPriceEl.addEventListener('change', function(e) {
    if (e.target.checked) {
      variantDetailsEl.classList.remove('d-none')
    } else {
      variantDetailsEl.classList.add('d-none')
    }
  })
}
