var createRedisClient = require("../RedisClient");

var app = require('http').createServer(handler);
var url = require('url') ;
var io = require('socket.io')(app, {path: '/order-tracking/socket.io'});
var fs = require('fs');
var path = require('path');
var _ = require('underscore');
var Mustache = require('mustache');

var ROOT_DIR = __dirname + '/../../..';

var envMap = {
  production: 'prod',
  development: 'dev',
  test: 'test'
};

var ConfigLoader = require('../ConfigLoader');

try {

  var configFile = 'config.yml';
  if (envMap[process.env.NODE_ENV]) {
    configFile = 'config_' + envMap[process.env.NODE_ENV] + '.yml';
  }

  var configLoader = new ConfigLoader(path.join(ROOT_DIR, '/app/config/', configFile));
  var config = configLoader.load();

} catch (e) {
  throw e;
}

var redis = require('redis').createClient({
  prefix: config.snc_redis.clients.default.options.prefix,
  url: config.snc_redis.clients.default.dsn
});

var redisPubSub = require('../RedisClient')({
  prefix: config.snc_redis.clients.default.options.prefix,
  url: config.snc_redis.clients.default.dsn
});

console.log('---------------------------');
console.log('- STARTING ORDER TRACKING -');
console.log('---------------------------');

console.log('NODE_ENV = ' + process.env.NODE_ENV);
console.log('PORT = ' + process.env.PORT);

var started = false;
var deliveries = {};

redisPubSub.prefixedSubscribe('delivery_events');
redisPubSub.prefixedSubscribe('order_events');

redisPubSub.on('message', function(channel, message) {
  let data = JSON.parse(message);

  if (channel === 'delivery_events') {
    var deliveryKey = 'delivery:' + data.delivery;
    if (deliveries[deliveryKey]) {
      deliveries[deliveryKey].socket.emit('delivery_event', data);
    }
  } else if (channel === 'order_events' && data.delivery) {
    var deliveryKey = 'delivery:' + data.delivery;
    if (deliveries[deliveryKey]) {
      deliveries[deliveryKey].socket.emit('order_event', data);
    }
  }
});

app.listen(process.env.PORT || 8002);

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

function updateObjects() {

  redis.hgetall('deliveries:delivering', (err, values) => {
    _.each(values, (courierKey, deliveryKey) => {
      if (!deliveries[deliveryKey]) {
        return;
      }

      redis.geopos('couriers:geo', courierKey, function(err, coords) {
        if (err) throw err;

        if (!coords || coords.length === 0 || !coords[0]) return;

        deliveries[deliveryKey].socket.emit('courier', {
          key: courierKey,
          coords: {
            lng: parseFloat(coords[0][0]),
            lat: parseFloat(coords[0][1])
          }
        });
      });
    });

    setTimeout(updateObjects, 1000);
  });
}

io.on('connection', function (socket) {
  if (!started) {
    console.log('A client is connected, start loop...');
    started = true;
    updateObjects();
  }

  var key;
  socket.on('delivery', function (delivery) {
    key = 'delivery:' + delivery['@id'].replace('/api/deliveries/', '');
    deliveries[key] = _.extend(delivery, {
      socket: socket
    });
    console.log('Clients connected: ' + _.size(deliveries));
  });

  socket.on('disconnect', function () {
    delete deliveries[key];
    console.log('Clients connected: ' + _.size(deliveries));
  });
});
