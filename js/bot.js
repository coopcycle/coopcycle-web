/**
 * A Bot to simulate courier activity.
 * It uses a GPX file to determine its itinerary.
 */
var fs = require('fs');
var _ = require('underscore');
var WebSocket = require('ws');
var fetch = require('node-fetch');
var FormData = require('form-data');
var parseXML = require('xml2js').parseString;

var ws;
var token;

var username = process.argv[2];
var password = process.argv[3];
var gpxFile = process.argv[4];
var httpBaseURL = process.argv[5];
var wsBaseURL = httpBaseURL.startsWith('http://') ? httpBaseURL.replace('http://', 'ws://') : httpBaseURL.replace('https://', 'wss://');

var formData  = new FormData();
formData.append("_username", username);
formData.append("_password", password);
var request = new fetch.Request(httpBaseURL + '/api/login_check', {
  method: 'POST',
  body: formData
});

var timeout;

var xml = fs.readFileSync(gpxFile);
var points = [];
var index = 0;

function next_position() {
  if (index > (points.length - 1)) {
    index = 0;
  }
  var position = points[index];
  ++index;

  return position;
}

function update_coords() {
  if (ws.readyState === WebSocket.OPEN) {
    var position = next_position();
    console.log('Sendind position', position);
    ws.send(JSON.stringify({
      type: "updateCoordinates",
      coordinates: position
    }));
  }
  timeout = setTimeout(update_coords, 1500);
}

function ws_connect() {

  ws = new WebSocket(wsBaseURL + '/realtime', '', {
    headers: {
      Authorization: "Bearer " + token
    }
  });

  ws.onopen = function() {
    console.log('Connected to server!');
    clearTimeout(timeout);
    update_coords();
  }

  ws.onmessage = function(e) {
    console.log(e.data);
  }

  ws.onclose = function() {
    console.log('Connection closed!');
    clearTimeout(timeout);
    setTimeout(ws_connect, 500);
  }

  ws.onerror = function(err) {
    console.log('Connection error!');
    clearTimeout(timeout);
  }
}

console.log('Loading GPX file...');
parseXML(xml, function (err, result) {

  _.each(result.gpx.wpt, function(point) {
    points.push({
      latitude: point['$'].lat,
      longitude: point['$'].lon
    })
  });

  // Start at random position
  index = _.random(0, (points.length - 1));

  console.log('Fetching token...');

  fetch(request).then(function(response) {
    if (response.ok) {
      return response.json().then(function(user) {
        token = user.token;
        console.log('Token ' + token);
        ws_connect();
      });
    } else {
      return response.json().then(function(json) {
        console.log(json.message);
      });
    }
  });
});
