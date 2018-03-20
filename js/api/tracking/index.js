var path = require('path');
var _ = require('lodash');
var http = require('http');

var winston = require('winston')
winston.level = process.env.NODE_ENV === 'production' ? 'info' : 'debug'

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
  'online': {
    toJSON: false,
    psubscribe: false
  },
  'offline': {
    toJSON: false,
    psubscribe: false
  },
  'tracking': {
    toJSON: true,
    psubscribe: false
  },
  'delivery_events': {
    toJSON: true,
    psubscribe: false
  },
  'order_events': {
    toJSON: true,
    psubscribe: false
  },
  'autoscheduler:begin_delivery': {
    toJSON: true,
    psubscribe: false
  },
  'autoscheduler:end_delivery': {
    toJSON: true,
    psubscribe: false
  },
  'task:done': {
    toJSON: true,
    psubscribe: false
  },
  'task:failed': {
    toJSON: true,
    psubscribe: false
  },
  'restaurant:*:orders': {
    toJSON: true,
    psubscribe: true
  },
}

io.on('connection', function (socket) {

  if (!subscribed) {

    winston.info('A client is connected, subscribing...');

    sub.on('subscribe', (channel, count) => {
      winston.info(`Subscribed to ${channel}`)
    })

    sub.on('psubscribe', (channel, count) => {
      winston.info(`Subscribed to ${channel}`)
    })

    sub.on('message', function(channelWithPrefix, message) {

      winston.debug(`Received message on channel ${channelWithPrefix}`)

      const channel = sub.unprefixedChannel(channelWithPrefix)
      const { toJSON } = channels[channel]

      io.sockets.emit(channel, toJSON ? JSON.parse(message) : message)

    })

    sub.on('pmessage', function(patternWithPrefix, channelWithPrefix, message) {

      winston.debug(`Received pmessage on channel ${channelWithPrefix}`)

      const channel = sub.unprefixedChannel(channelWithPrefix)
      const pattern = sub.unprefixedChannel(patternWithPrefix)
      const { toJSON } = channels[pattern]

      io.sockets.emit(channel, toJSON ? JSON.parse(message) : message)

    })

    _.each(channels, (options, channel) => {
      const { psubscribe } = options
      if (psubscribe) {
        sub.prefixedPSubscribe(channel)
      } else {
        sub.prefixedSubscribe(channel)
      }
    })

    subscribed = true;

  }

})
