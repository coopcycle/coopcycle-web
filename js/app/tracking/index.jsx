import React from 'react';
import {render} from 'react-dom';
import DeliveryList from './DeliveryList.jsx';
import ConnectCounter from './ConnectCounter'
import MapHelper from '../MapHelper'
import _ from 'lodash'
import L from 'leaflet'
import moment from 'moment'

const locale = $('html').attr('lang')
moment.locale(locale)

var deliveries = [];
var couriers = [];
var deliveryList;

var center = {
  lat: 48.857498,
  lng: 2.335402
};
var zoom = window.mapZoom || 13;
var map = MapHelper.init('map', center, zoom)

const hostname = window.location.hostname

const couriersMap = new Map()
const layersByDelivery = new Map()
const couriersLayer = new L.LayerGroup()

function hideOthers(delivery) {
  layersByDelivery.forEach((layers, key) => {
    if (key !== delivery.id) {
      map.removeLayer(layers)
    } else {
      if (!map.hasLayer(layers)) {
        layers.addTo(map)
      }
    }
  })
}

function fitMap() {
  const layers = []
  layersByDelivery.forEach((layerGroup, key) => {
    layerGroup.getLayers().forEach(layer => layers.push(layer))
  })
  couriersLayer.getLayers().forEach(layer => layers.push(layer))
  MapHelper.fitToLayers(map, layers)
}

function createLayerGroup(delivery, pickupCoords, dropoffCoords, color) {

  const { originAddress, deliveryAddress } = delivery

  const pickup = MapHelper.createMarker(pickupCoords, 'arrow-up', 'marker', color || '#3498DB')
  const dropoff = MapHelper.createMarker(dropoffCoords, 'arrow-down', 'marker', color || '#2ECC71')

  pickup.bindPopup(`<div class="text-center">${originAddress.streetAddress}</div>`, {
    offset: [3, 70]
  })
  dropoff.bindPopup(`<div class="text-center">${deliveryAddress.streetAddress}</div>`, {
    offset: [3, 70]
  })

  const layerGroup = new L.LayerGroup([])

  layerGroup.addLayer(pickup)
  layerGroup.addLayer(dropoff)

  return layerGroup
}

window.AppData.Tracking.deliveries
  .forEach(delivery => {
    const { originAddress, deliveryAddress } = delivery
    const layerGroup = createLayerGroup(delivery,
      [ originAddress.geo.latitude, originAddress.geo.longitude ],
      [ deliveryAddress.geo.latitude, deliveryAddress.geo.longitude ]
    )
    layersByDelivery.set(delivery.id, layerGroup)
    layerGroup.eachLayer(layer => layer.addTo(map))
  })
couriersLayer.addTo(map)
fitMap()

const counter = render(<ConnectCounter />, document.getElementById('menu-heading'))

deliveryList = render(
  <DeliveryList
    deliveries={ window.AppData.Tracking.deliveries }
    onItemClick={(delivery) => {

      const { originAddress, deliveryAddress } = delivery

      var originParam = [ originAddress.geo.latitude, originAddress.geo.longitude ].join(',');
      var destinationParam = [ deliveryAddress.geo.latitude, deliveryAddress.geo.longitude ].join(',')

      const layerGroup = layersByDelivery.get(delivery.id)

      if (layerGroup.getLayers().length < 3) {
        const [ marker1, marker2 ] = layerGroup.getLayers()
        MapHelper.getPolyline(marker1, marker2)
          .then((data) => {
            const polyline = new L.Polyline(data, {
              color: "#BDC3C7",
              weight: 3,
              opacity: 0.6,
              smoothFactor: 1
            })
            layerGroup.addLayer(polyline)
            hideOthers(delivery)
          });
      } else {
        hideOthers(delivery)
      }

    }} />, document.getElementById('order-list')
)

setTimeout(function() {

  const socket = io('//' + hostname, { path: '/tracking/socket.io' })

  socket.on('tracking', data => {
    let marker
    if (!couriersMap.has(data.user)) {
      marker = MapHelper.createMarker(data.coords, 'bicycle', 'circle', '#000')
      const popupContent = `<div class="text-center">${data.user}</div>`
      marker.bindPopup(popupContent, {
        offset: [3, 70]
      })
      couriersLayer.addLayer(marker)
      couriersMap.set(data.user, marker)
      counter.increment()
      fitMap()
    } else {
      marker = couriersMap.get(data.user)
      marker.setLatLng(data.coords).update()
      marker.setIcon(MapHelper.createMarkerIcon('bicycle', 'circle', '#000'))
    }
  })

  socket.on('online', username => {
    console.log(`User ${username} is connected`)
    counter.increment()
  })

  socket.on('offline', username => {
    if (!couriersMap.has(username)) {
      console.error(`User ${username} not found`)
      return
    }
    const marker = couriersMap.get(username)
    marker.setIcon(MapHelper.createMarkerIcon('bicycle', 'circle', '#CCC'))
    counter.decrement()
  })

  socket.on('autoscheduler:begin_delivery', delivery => {

    const { originAddress, deliveryAddress } = delivery

    if (!layersByDelivery.has(delivery.id)) {
      return
    }

    const layerGroup = layersByDelivery.get(delivery.id)
    const newLayerGroup = createLayerGroup(delivery,
      [ originAddress.position.latitude, originAddress.position.longitude ],
      [ deliveryAddress.position.latitude, deliveryAddress.position.longitude ],
      '#E74C3C'
    )
    setTimeout(() => {
      map.removeLayer(layerGroup)
      newLayerGroup.eachLayer(layer => layer.addTo(map))
      layersByDelivery.set(delivery.id, newLayerGroup)
    }, 150)
  })

  socket.on('autoscheduler:end_delivery', delivery => {

    const { originAddress, deliveryAddress } = delivery

    if (!layersByDelivery.has(delivery.id)) {
      return
    }

    const layerGroup = layersByDelivery.get(delivery.id)
    const newLayerGroup = createLayerGroup(delivery,
      [ originAddress.position.latitude, originAddress.position.longitude ],
      [ deliveryAddress.position.latitude, deliveryAddress.position.longitude ]
    )
    setTimeout(() => {
      map.removeLayer(layerGroup)
      newLayerGroup.eachLayer(layer => layer.addTo(map))
      layersByDelivery.set(delivery.id, newLayerGroup)
    }, 500)
  })

  socket.on('delivery_events', event => {
    console.log('New delivery event', event)
    if (event.status === 'CANCELED' || event.status === 'DELIVERED') {
      deliveryList.removeItemById(event.delivery)
    }
    if (event.status === 'DISPATCHED' || event.status === 'PICKED') {
      deliveryList.updateStatusById(event.delivery, event.status)
    }
    if (event.status === 'WAITING') {
      deliveryList.addItem(event.delivery, event.status)
      const { originAddress, deliveryAddress } = event.delivery
      const layerGroup = createLayerGroup(delivery,
        [ originAddress.geo.latitude, originAddress.geo.longitude ],
        [ deliveryAddress.geo.latitude, deliveryAddress.geo.longitude ]
      )
      layersByDelivery.set(delivery.id, layerGroup)
      layerGroup.eachLayer(layer => layer.addTo(map))
      fitMap()
    }
  })

}, 1000);
