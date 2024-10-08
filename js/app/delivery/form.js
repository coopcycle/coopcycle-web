import MapHelper from '../MapHelper'
import L from 'leaflet'
import _ from 'lodash'
import JsBarcode from 'jsbarcode'
require('gasparesganga-jquery-loading-overlay')

import DeliveryForm from '../forms/delivery'
import PricePreview from './PricePreview'
import axios from 'axios'

const baseURL = location.protocol + '//' + location.host

import './form.scss'

let map
let polylineLayerGroup
let markersLayerGroup

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

const priceEl = document.getElementById('delivery-price')
let pricePreview
if (priceEl) {
  pricePreview = new PricePreview(priceEl)
}

const arbitraryPriceEl = document.getElementById('delivery_arbitraryPrice')

if (arbitraryPriceEl) {
  const containerEl = document.querySelector('[data-variant-details]')
  const variantNameEl = document.getElementById('delivery_variantName')
  const variantPriceEl = document.getElementById('delivery_variantPrice')

  const setIsChecked = (isChecked) => {
    if (isChecked) {
      containerEl.classList.remove('d-none')
      variantNameEl.setAttribute('required', 'required');
      variantPriceEl.setAttribute('required', 'required');
    } else {
      containerEl.classList.add('d-none')
      variantNameEl.removeAttribute('required');
      variantPriceEl.removeAttribute('required');
    }
  }

  // update on initial load
  setIsChecked(arbitraryPriceEl.checked)

  arbitraryPriceEl.addEventListener('change', function(e) {
    setIsChecked(e.target.checked)
  })
}

const updateData = (form, delivery) => {
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

    form.disable()
    polylineLayerGroup.clearLayers()

    const promises = []

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

    promises.push(updateDistance)
    promises.push(updatePrice)

    Promise.all(promises)
    .then(() => {
      form.enable()
    })
    // eslint-disable-next-line no-console
    .catch(e => console.error(e))
  }
}

const checkSuggestionsOnSubmit = async (form, formHTMLEl, delivery) => {
  form.disable()

  if (!delivery.tasks.length > 2) {
    formHTMLEl.submit()
  }

  const jwtResp = await $.getJSON(window.Routing.generate('profile_jwt'))
  const jwt = jwtResp.jwt
  const url = `${baseURL}/api/deliveries/suggest_optimizations` 
  const response = await axios.post(
    url, 
    {
      ...delivery,
      tasks: delivery.tasks.slice(0).map(t => ({
        ...t,
        address: serializeAddress(t.address)
      }))
    },
    {
      headers: {
        Accept: 'application/ld+json',
        'Content-Type': 'application/ld+json',
        Authorization: `Bearer ${jwt}`
      }
    })
  
  if (response.data.suggestions.length > 0) {
    form.showSuggestions(response.data.suggestions)
  } else {
    formHTMLEl.submit()
  }
}

new DeliveryForm('delivery', {
  onReady: function(delivery) {
    updateData(this, delivery)
  },
  onChange: function(delivery) {
    updateData(this, delivery)
  },
  onSubmit: function(formHTMLEl, delivery) {
    checkSuggestionsOnSubmit(this, formHTMLEl, delivery)
  }
})
