var app = require('http').createServer(handler);
var url = require('url') ;
var io = require('socket.io')(app, {path: '/order-tracking/socket.io'});
var fs = require('fs');
var _ = require('underscore');
var Mustache = require('mustache');
var Promise = require('promise');

var ROOT_DIR = __dirname + '/../../..';

var envMap = {
  production: 'prod',
  development: 'dev',
  test: 'test'
}

var ConfigLoader = require('../ConfigLoader');

try {

  var configFile = 'config.yml';
  if (envMap[process.env.NODE_ENV]) {
    configFile = 'config_' + envMap[process.env.NODE_ENV] + '.yml';
  }

  var configLoader = new ConfigLoader(ROOT_DIR + '/app/config/' + configFile);
  var config = configLoader.load();

} catch (e) {
  throw e;
}

var redis = require('redis').createClient({
  url: config.snc_redis.clients.default.dsn
});
var redisPubSub = require('redis').createClient({
  url: config.snc_redis.clients.default.dsn
});

console.log('---------------------------');
console.log('- STARTING ORDER TRACKING -');
console.log('---------------------------');

console.log('NODE_ENV = ' + process.env.NODE_ENV)
console.log('PORT = ' + process.env.PORT)

var started = false;
var orders = {};

redisPubSub.subscribe('order_events');
redisPubSub.on('message', function(channel, message) {
  if (channel === 'order_events') {
    var data = JSON.parse(message);
    var orderKey = 'order:' + data.order;
    if (orders[orderKey]) {
      orders[orderKey].socket.emit('order_event', data);
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

  console.time("Loading data from Redis");

  redis.hgetall('orders:delivering', (err, values) => {
    _.each(values, (courierKey, orderKey) => {
      if (!orders[orderKey]) {
        return;
      }

      redis.geopos('couriers:geo', courierKey, function(err, coords) {
        if (err) throw err;

        if (!coords || coords.length === 0 || !coords[0]) return;

        orders[orderKey].socket.emit('courier', {
          key: courierKey,
          coords: {
            lng: parseFloat(coords[0][0]),
            lat: parseFloat(coords[0][1])
          }
        });
      });
    });

    console.timeEnd("Loading data from Redis");
    setTimeout(updateObjects, 1000);
  })
}

io.on('connection', function (socket) {
  if (!started) {
    console.log('A client is connected, start loop...');
    started = true;
    updateObjects();
  }

  var key;
  socket.on('order', function (order) {
    key = 'order:' + order['@id'].replace('/api/orders/', '');
    orders[key] = _.extend(order, {
      socket: socket
    });
    console.log('Clients connected: ' + _.size(orders));
  });

  socket.on('disconnect', function () {
    delete orders[key];
    console.log('Clients connected: ' + _.size(orders));
  });
});