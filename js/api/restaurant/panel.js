var app = require('http').createServer(handler);
var io = require('socket.io')(app, {path: '/restaurant-panel/socket.io'});
var path = require('path');
var _ = require('underscore');

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

var redisPubSub = require('../RedisClient')({
  prefix: config.snc_redis.clients.default.options.prefix,
  url: config.snc_redis.clients.default.dsn
});

let restaurants = {};

redisPubSub.on('message', function(channel, message) {

  console.log('Received message on channel ' + channel);

  const order = JSON.parse(message);

  const restaurantKey = 'restaurant:' + order.restaurant['@id'].replace('/api/restaurants/', '');
  console.log('Emit to restaurant ' + restaurantKey);
  if (restaurants[restaurantKey]) {
    restaurants[restaurantKey].socket.emit('order', order);
  } else {
    console.log(console.log('Restaurant with key ' + restaurantKey) + ' does not exist')
  }

});

console.log('-----------------------------');
console.log('- STARTING RESTAURANT PANEL -');
console.log('-----------------------------');

console.log('NODE_ENV = ' + process.env.NODE_ENV);
console.log('PORT = ' + process.env.PORT);
console.log('ASSETS URL = ' + process.env.ASSETS_BASE_URL);

app.listen(process.env.PORT || 8003);

function handler(req, res) {
  res.writeHead(200);
  res.end('');
}

io.on('connection', function (socket) {

  socket.on('restaurant', function (restaurant) {

    const id = restaurant['@id'].replace('/api/restaurants/', '');
    const key = 'restaurant:' + id;

    restaurants[key] = _.extend(restaurant, {
      socket: socket
    });

    console.log('Clients connected: ' + _.size(restaurants));
    console.log('Subscribe to restaurant #' + id + ' orders');

    const channel = 'restaurant:' + id + ':orders';
    redisPubSub.prefixedSubscribe(channel);

    socket.on('close', function() {
      console.log('Restaurant #' + id + ' disconnected!');
      redisPubSub.prefixedSubscribe(channel);

      delete restaurants[key];
      console.log('Clients connected: ' + _.size(restaurants));
    });

  });

});
