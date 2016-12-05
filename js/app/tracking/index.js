var MarkerIcons = require('fontawesome-markers');

var map;
var markers = [];
var infoWindows = [];
var center = {
  lat: 48.857498,
  lng: 2.335402
};

function addMarker(key, position, isCourier) {
  var marker = _.find(markers, function(marker) {
    return marker.key === key;
  });

  if (!marker) {

    var infoWindow = new google.maps.InfoWindow({
      content: key
    });
    infoWindows.push(infoWindow);

    var marker = {
      key: key,
      marker: new google.maps.Marker({
        position: position,
        map: map,
        animation: google.maps.Animation.DROP,
        icon: {
          path: isCourier ? MarkerIcons.BICYCLE : MarkerIcons.CUTLERY,
          scale: 0.333333,
          strokeWeight: 0,
          strokeColor: '#000',
          strokeOpacity: 1,
          fillColor: isCourier ? '#27ae60' : '#000',
          fillOpacity: 1,
        },
      })
    };

    marker.marker.addListener('click', function() {
      closeAllInfoWindows();
      infoWindow.open(map, marker.marker);
    });

    markers.push(marker);

  } else {
    marker.marker.setPosition(position);
  }
}

function closeAllInfoWindows() {
  _.each(infoWindows, function(infoWindow) {
    infoWindow.close();
  });
}

function removeMarkersByKeys(keys) {
  _.each(keys, function(key) {
    var index = _.findIndex(markers, function(marker) {
      return marker.key === key;
    });
    if (-1 !== index) {
      var marker = markers.splice(index, 1);
      marker[0].marker.setMap(null);
    }
  });
}

window.initMap = function() {
  map = new google.maps.Map(document.getElementById('map'), {
    center: center,
    zoom: 13
  });

  var hostname = window.location.hostname;

  setTimeout(function() {
    var socket = io('//' + hostname, {path: '/tracking/socket.io'});
    socket.on('news', function (data) {
      var allKeys = _.map(data, function(object) {
        return object.key;
      });
      var onMapKeys = _.map(markers, function(marker) {
        return marker.key;
      });
      var keysToRemove = _.difference(onMapKeys, allKeys);

      removeMarkersByKeys(keysToRemove);

      for (var i = 0; i < data.length; i++) {
        addMarker(data[i].key, data[i].coords, data[i].key.startsWith('courier:'));
      }
    });
  }, 1000);
}