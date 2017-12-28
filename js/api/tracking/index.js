var fs = require('fs');
var path = require('path');
var url = require('url');
var _ = require('underscore');
var http = require('http');

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

const sub = require('../RedisClient')({
  prefix: config.snc_redis.clients.default.options.prefix,
  url: config.snc_redis.clients.default.dsn
});

console.log('---------------------');
console.log('- STARTING TRACKING -');
console.log('---------------------');

console.log('NODE_ENV = ' + process.env.NODE_ENV);
console.log('PORT = ' + process.env.PORT);

const app = http.createServer(function(request, response) {
    // process HTTP request. Since we're writing just WebSockets server
    // we don't have to implement anything.
});
app.listen(process.env.PORT || 8001);
const io = require('socket.io')(app, { path: '/tracking/socket.io' });

let subscribed = false;

io.on('connection', function (socket) {

  if (!subscribed) {

    console.log('A client is connected, subscribing...');

    sub.prefixedSubscribe('online')
    sub.prefixedSubscribe('offline')
    sub.prefixedSubscribe('tracking')
    sub.prefixedSubscribe('delivery_events')
    sub.prefixedSubscribe('order_events')

    sub.on('subscribe', (channel, count) => {
      if (count == 5) {
        sub.on('message', function(channel, message) {
          if (sub.isChannel(channel, 'online')) {
            io.sockets.emit('online', message)
          }
          if (sub.isChannel(channel, 'offline')) {
            io.sockets.emit('offline', message)
          }
          if (sub.isChannel(channel, 'tracking')) {
            io.sockets.emit('tracking', JSON.parse(message))
          }
          if (sub.isChannel(channel, 'delivery_events')) {
            io.sockets.emit('delivery_events', JSON.parse(message))
          }
          if (sub.isChannel(channel, 'order_events')) {
            io.sockets.emit('order_events', JSON.parse(message))
          }
        })
      }
    })

    subscribed = true;

  }

})
