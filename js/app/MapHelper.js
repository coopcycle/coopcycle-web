var L = require('leaflet');
var Polyline = require('@mapbox/polyline');
var BeautifyMarker = require('beautifymarker');

function init(id, center, zoom, zoomControl = true) {
  var map = L.map(id, { scrollWheelZoom: false, zoomControl: zoomControl}).setView([center.lat, center.lng], zoom);

  L.tileLayer('https://cartodb-basemaps-{s}.global.ssl.fastly.net/rastertiles/voyager/{z}/{x}/{y}.png', {
    maxZoom: 18,
    attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>, &copy;<a href="https://carto.com/attribution">CARTO</a>'
  }).addTo(map);

  return map;
}

function createMarkerIcon(icon, iconShape, color) {
  return L.BeautifyIcon.icon({
    icon: icon,
    iconShape: iconShape,
    borderColor: color,
    textColor: color,
    backgroundColor: 'transparent'
  });
}

function createMarker(position, icon, iconShape, color) {
  var marker = L.marker([position.lat, position.lng], {
    icon: createMarkerIcon(icon, iconShape, color)
  });

  return marker;
}

function fitToLayers(map, layers, pad = 0) {
  var group = new L.featureGroup(layers);
  map.fitBounds(group.getBounds().pad(pad));
}

function decodePolyline(polyline) {
  var steps = Polyline.decode(polyline);
  var polylineCoords = [];

  for (var i = 0; i < steps.length; i++) {
    var tempLocation = new L.LatLng(
      steps[i][0],
      steps[i][1]
    );
    polylineCoords.push(tempLocation);
  }

  return polylineCoords;
}

function getPolyline(origin, destination) {
  var originLatLng = origin.getLatLng();
  var destinationLatLng = destination.getLatLng();

  var params = {
    origin: [originLatLng.lat, originLatLng.lng].join(','),
    destination: [destinationLatLng.lat, destinationLatLng.lng].join(',')
  };

  return fetch('/api/routing/route?origin=' + params.origin + '&destination=' + params.destination)
    .then((response) => {
      return response.json().then((data) => {
        return decodePolyline(data.routes[0].geometry);
      })
    });
}

module.exports = {
  init: init,
  createMarker: createMarker,
  createMarkerIcon: createMarkerIcon,
  fitToLayers: fitToLayers,
  decodePolyline: decodePolyline,
  getPolyline: getPolyline
};
