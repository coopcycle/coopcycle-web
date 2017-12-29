var MapHelper = require('../MapHelper');

if ($('#map').is(':visible')) {
  let map = MapHelper.init('map', null, 10, false),
    restaurantMarker = MapHelper.createMarker(window.AppData.restaurantCoordinates, 'cutlery', 'marker', '#337ab7'),
    customerMarker = MapHelper.createMarker(window.AppData.customerCoordinates, 'user', 'marker', '#337ab7');

  MapHelper.getPolyline(restaurantMarker, customerMarker)
    .then((data) => {

      restaurantMarker.addTo(map);
      customerMarker.addTo(map);

      MapHelper.fitToLayers(map, [restaurantMarker, customerMarker], 1);
      map.panBy([220, -150], {animate: false});

      var polyline = new L.Polyline(data, {
        color: '#337ab7',
        weight: 3,
        opacity: 0.8,
        smoothFactor: 1
      });
      map.addLayer(polyline);
    });
}
