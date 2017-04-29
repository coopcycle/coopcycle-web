var MapHelper = require('../MapHelper');
require('leaflet-draw')

var center = {
  lat: 48.857498,
  lng: 2.335402
};
var zoom = window.mapZoom || 13;

const map = MapHelper.init('map', center, zoom);

var editableLayers = L.geoJSON(); // new L.FeatureGroup();
map.addLayer(editableLayers);

function saveGeoJSON() {
  console.log('saveGeoJSON')
  const geoJSON = editableLayers.toGeoJSON()
  localStorage.setItem('zones', JSON.stringify(geoJSON))
}

const options = {
  position: 'topright',
  draw: {
    circle: false, // Turns off this drawing tool
    marker: false,
    polyline: false,
    polygon: {
      allowIntersection: false, // Restricts shapes to simple polygons
      drawError: {
        color: '#e1e100', // Color the shape will turn when intersects
        message: '<strong>Oh snap!<strong> you can\'t draw that!' // Message that will show when intersect
      },
      shapeOptions: {
        color: '#bada55'
      }
    },
    rectangle: {
      shapeOptions: {
          clickable: false
      }
    },
  },
  edit: {
    featureGroup: editableLayers, //REQUIRED!!
    remove: true
  }
};

var drawControl = new L.Control.Draw(options);
map.addControl(drawControl);

// let data = localStorage.getItem('zones') || '[]';
// let zones = JSON.parse(data);

const geoJSON = JSON.parse(localStorage.getItem('zones'))
if (geoJSON) {
  editableLayers.addData(geoJSON)
}

console.log(editableLayers);

map.on(L.Draw.Event.CREATED, function (e) {
  editableLayers.addLayer(e.layer);
  saveGeoJSON();
});

map.on(L.Draw.Event.EDITED, function (e) {
  saveGeoJSON();
});

map.on(L.Draw.Event.DELETED, function (e) {
  saveGeoJSON();
});
