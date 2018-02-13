var path = require('path');
var _ = require('lodash');
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

const channels = {
  'online': false,
  'offline': false,
  'tracking': true,
  'delivery_events': true,
  'order_events': true,
  'autoscheduler:begin_delivery': true,
  'autoscheduler:end_delivery': true,
  'task:done': true,
  'task:failed': true,
}

io.on('connection', function (socket) {

  if (!subscribed) {

    console.log('A client is connected, subscribing...');

    _.each(channels, (toJSON, channel) => sub.prefixedSubscribe(channel))

    sub.on('subscribe', (channel, count) => {
      if (count == _.size(channels)) {

        console.log('All channels subscribed, start forwarding messages')

        sub.on('message', function(channelWithPrefix, message) {
          _.each(channels, (toJSON, channel) => {
            if (sub.isChannel(channelWithPrefix, channel)) {
              io.sockets.emit(channel, toJSON ? JSON.parse(message) : message)
            }
          })
        })
      }
    })

    subscribed = true;

  }

})
