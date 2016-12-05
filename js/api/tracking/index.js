var app = require('http').createServer(handler)
var io = require('socket.io')(app, {path: '/tracking/socket.io'});
var fs = require('fs');
var _ = require('underscore');
var Promise = require('promise');
var redis = require('redis').createClient();

app.listen(8001);

function handler (req, res) {
  fs.readFile(__dirname + '/index.html',
  function (err, data) {
    if (err) {
      res.writeHead(500);
      return res.end('Error loading index.html');
    }

    res.writeHead(200);
    res.end(data);
  });
}

function updateObjects(socket) {
  var promises = [];
  redis.zrange('GeoSet', 0, -1, function(err, results) {
    _.each(results, function(key) {
      var promise = new Promise(function(resolve, reject) {
        redis.geopos('GeoSet', key, function(err, result) {
          resolve({
            key: key,
            coords: {
              lat: parseFloat(result[0][0]),
              lng: parseFloat(result[0][1])
            }
          });
        });
      });
      promises.push(promise);
    });
    Promise.all(promises).then(function(values) {
      socket.emit('news', values);
      setTimeout(function() {
        updateObjects(socket);
      }, 1000);
    });
  });
}

io.on('connection', function (socket) {
  setTimeout(function() {
    updateObjects(socket);
  }, 1000);
});