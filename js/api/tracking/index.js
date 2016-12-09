var app = require('http').createServer(handler);
var url = require('url') ;
var io = require('socket.io')(app, {path: '/tracking/socket.io'});
var fs = require('fs');
var _ = require('underscore');
var redis = require('redis').createClient();
var Mustache = require('mustache');
var Promise = require('promise');

console.log('---------------------');
console.log('- STARTING TRACKING -');
console.log('---------------------');

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

  console.time("Loading data from Redis");

  var promises = [];

  promises.push(new Promise(function(resolve, reject) {
    redis.lrange('orders:waiting', 0, -1, function(err, keys) {
      keys = keys.map(function(key) { return 'order:' + key; });
      redis.geopos('orders:geo', keys, function(err, values) {
        var hash = _.object(keys, values);
        var objects = _.map(hash, function(value, key) {
          return {
            key: key,
            coords: {
              lng: parseFloat(value[0]),
              lat: parseFloat(value[1])
            }
          }
        });
        resolve(objects);
      });
    });
  }));

  promises.push(new Promise(function(resolve, reject) {
    redis.lrange('orders:dispatching', 0, -1, function(err, keys) {
      keys = keys.map(function(key) { return 'order:' + key; });
      redis.geopos('orders:geo', keys, function(err, values) {
        var hash = _.object(keys, values);
        var objects = _.map(hash, function(value, key) {
          return {
            key: key,
            coords: {
              lng: parseFloat(value[0]),
              lat: parseFloat(value[1])
            }
          }
        });
        resolve(objects);
      });
    });
  }));

  promises.push(new Promise(function(resolve, reject) {
    redis.lrange('orders:delivering', 0, -1, function(err, keys) {
      keys = keys.map(function(key) { return 'order:' + key; });
      redis.geopos('orders:geo', keys, function(err, values) {
        var hash = _.object(keys, values);
        var objects = _.map(hash, function(value, key) {
          return {
            key: key,
            coords: {
              lng: parseFloat(value[0]),
              lat: parseFloat(value[1])
            }
          }
        });
        resolve(objects);
      });
    });
  }));

  promises.push(new Promise(function(resolve, reject) {
    redis.zrange('couriers:geo', 0, -1, function(err, keys) {
      redis.geopos('couriers:geo', keys, function(err, values) {
        var hash = _.object(keys, values);
        var objects = _.map(hash, function(value, key) {
          return {
            key: key,
            coords: {
              lng: parseFloat(value[0]),
              lat: parseFloat(value[1])
            }
          }
        });
        resolve(objects);
      });
    });
  }));

  promises.push(new Promise(function(resolve, reject) {
    redis.zrange('delivery_addresses:geo', 0, -1, function(err, keys) {
      redis.geopos('delivery_addresses:geo', keys, function(err, values) {
        var hash = _.object(keys, values);
        var objects = _.map(hash, function(value, key) {
          return {
            key: key,
            coords: {
              lng: parseFloat(value[0]),
              lat: parseFloat(value[1])
            }
          }
        });
        resolve(objects);
      });
    });
  }));

  Promise.all(promises).then(function(values) {

    console.timeEnd("Loading data from Redis");

    var waiting = values[0];
    var dispatching = values[1];
    var delivering = values[2];
    var couriers = values[3];
    var deliveryAddresses = values[4];

    waiting = waiting.map(function(order) {
      return _.extend(order, {state: 'WAITING'});
    });
    dispatching = dispatching.map(function(order) {
      return _.extend(order, {state: 'DISPATCHING'});
    });
    delivering = delivering.map(function(order) {
      return _.extend(order, {state: 'DELIVERING'});
    });

    var orders = waiting
      .concat(dispatching)
      .concat(delivering);

    socket.emit('orders', orders);
    socket.emit('couriers', couriers);
    socket.emit('delivery_addresses', deliveryAddresses);

    setTimeout(function() {
      updateObjects(socket);
    }, 1000);

  });

}

io.on('connection', function (socket) {
  setTimeout(function() {
    updateObjects(socket);
  }, 1000);
});