var app = require('http').createServer(handler);
var url = require('url') ;
var io = require('socket.io')(app, {path: '/tracking/socket.io'});
var fs = require('fs');
var _ = require('underscore');
var redis = require('redis').createClient();
var Mustache = require('mustache');

app.listen(8001);

function handler(req, res) {
  fs.readFile(__dirname + '/index.html', function (err, data) {
    if (err) {
      res.writeHead(500);
      return res.end('Error loading index.html');
    }

    var params = url.parse(req.url, true).query;

    var output = Mustache.render(data.toString('utf8'), {
      zoom: params.zoom || 13
    });

    res.writeHead(200);
    res.end(output);
  });
}

function updateObjects(socket) {
  redis.zrange('GeoSet', 0, -1, function(err, keys) {
    redis.geopos('GeoSet', keys, function(err, values) {
      var hash = _.object(keys, values);
      var objects = _.map(hash, function(value, key) {
          return {
            key: key,
            coords: {
              lat: parseFloat(value[0]),
              lng: parseFloat(value[1])
            }
          }
      });

      socket.emit('news', objects);

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