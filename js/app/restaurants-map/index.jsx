import _ from 'lodash'
import L from 'leaflet-providers'
require('beautifymarker')

import MapHelper from '../MapHelper'

import './index.scss'

var COLORS = {
  TURQUOISE: '#1ABC9C',
  GREEN_SEA: '#16A085',
  EMERALD: '#2ECC71',
  NEPHRITIS: '#27AE60',
  PETER_RIVER: '#3498DB',
  BELIZE_HOLE: '#2980B9',
  AMETHYST: '#9B59B6',
  WISTERIA: '#8E44AD',
  SUN_FLOWER: '#F1C40F',
  ORANGE: '#F39C12',
  CARROT: '#E67E22',
  PUMPKIN: '#D35400',
  ALIZARIN: '#E74C3C',
  POMEGRANATE: '#C0392B',
}

function createMarkerIcon(icon, iconShape, color) {
  return L.BeautifyIcon.icon({
    icon: icon,
    iconShape: iconShape,
    borderColor: color,
    textColor: color,
    backgroundColor: 'transparent'
  })
}

function createMarker(position, icon, iconShape, color) {

  var marker = L.marker([position.lat, position.lng], {
    icon: createMarkerIcon(icon, iconShape, color)
  })

  return marker
}

const map = MapHelper.init('map')

const markers = window.__restaurants.map(restaurant => {

  const pos = { lat: restaurant.address.geo.latitude, lng: restaurant.address.geo.longitude }
  var randomColor = COLORS[_.first(_.shuffle(_.keys(COLORS)))]

  const marker = createMarker(pos, 'cutlery', 'marker', randomColor)

  const el = $('<div />')

  if (window.parent) {
    el.on('click', '.restaurant-map-link', function(e) {
      e.preventDefault()
      window.parent.document.location.replace($(this).attr('href'))
    })
  }

  el.html(`<a href="${ restaurant.url }" class="restaurant-map-link">${ restaurant.name }</a>`)

  marker.bindPopup(el[0])

  return marker
})

if (markers.length > 0) {
  const restaurantsLayer = L.layerGroup(markers)
  restaurantsLayer.addTo(map)

  const group = new L.featureGroup(markers)
  map.fitBounds(group.getBounds())
}
