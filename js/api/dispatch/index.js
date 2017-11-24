var WebSocketServer = require('ws').Server;
var http = require('http');
var fs = require('fs');
var Sequelize = require('sequelize');

var winston = require('winston');
winston.level = process.env.NODE_ENV === 'production' ? 'info' : 'debug';

var ROOT_DIR = __dirname + '/../../..';

console.log('---------------------');
console.log('- STARTING DISPATCH -');
console.log('---------------------');

console.log('NODE_ENV = ' + process.env.NODE_ENV);
console.log('PORT = ' + process.env.PORT);

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

  var configLoader = new ConfigLoader(ROOT_DIR + '/app/config/' + configFile);
  var config = configLoader.load();

} catch (e) {
  throw e;
}

var cert = fs.readFileSync(ROOT_DIR + '/var/jwt/public.pem');

var redis = require('redis').createClient({
  prefix: config.snc_redis.clients.default.options.prefix,
  url: config.snc_redis.clients.default.dsn
});

var redisPubSub = require('../RedisClient')({
  prefix: config.snc_redis.clients.default.options.prefix,
  url: config.snc_redis.clients.default.dsn
});

var sequelize = new Sequelize(
  config.doctrine.dbal.dbname,
  config.doctrine.dbal.user,
  config.doctrine.dbal.password,
  {
    host: config.doctrine.dbal.host,
    dialect: 'postgres',
    logging: false,
  }
);

var server = http.createServer(function(request, response) {
    // process HTTP request. Since we're writing just WebSockets server
    // we don't have to implement anything.
});
server.listen(process.env.PORT || 8000, function() {});

var Db = require('../Db')(sequelize);

var Courier = require('../models/Courier').Courier;
Courier.init(redis, redisPubSub);

var Delivery = require('../models/Delivery');
Delivery.init(redis, sequelize, Db);

var DeliveryDispatcher = require('../models/DeliveryDispatcher');
var deliveryDispatcher = new DeliveryDispatcher(redis, Delivery.Registry);

var TokenVerifier = require('../TokenVerifier');
var tokenVerifier = new TokenVerifier(cert, Db);

/* Delivery dispatch loop */

deliveryDispatcher.setHandler(function(delivery, next) {

  // winston.info('Trying to dispatch delivery #' + delivery.id);

  Courier.nearestForDelivery(delivery, 3500).then(function(courier) {

    if (!courier) {
      // winston.debug('No couriers nearby');
      return next();
    }

    console.log('Dispatching delivery #' + delivery.id + ' to courier #' + courier.id);

    // There is a courier available
    // Change state to "DISPATCHING" and wait for feedback
    courier.setDelivery(delivery.id);
    courier.setState(Courier.DISPATCHING);

    // Remove delivery from the waiting list
    redis.lrem('deliveries:waiting', 0, delivery.id, function(err) {
      if (err) throw err;
      redis.lpush('deliveries:dispatching', delivery.id, function(err) {
        if (err) throw err;
        // TODO record dispatch event ?
        courier.send({
          type: 'delivery',
          delivery: {
            id: delivery.id,
            originAddress: delivery.originAddress.position,
            deliveryAddress: delivery.deliveryAddress.position
          }
        });
        next();
      });
    });
  });
});

// Load deliveries in Redis
Delivery.load().then(function() {
  console.log('Everything is loaded, starting dispatch loop...');
  deliveryDispatcher.start();
});

// create the server
wsServer = new WebSocketServer({
    server: server,
    verifyClient: function (info, cb) {
      tokenVerifier.verify(info, cb);
    },
});

var isClosing = false;

// WebSocket server
wsServer.on('connection', function(ws) {

    var courier = ws.upgradeReq.courier;
    courier.connect(ws);
    Courier.Pool.add(courier);

    console.log('Courier #' + courier.id + ' connected!');

    ws.on('message', function(messageText) {

      if (isClosing) {
        return;
      }

      var message = JSON.parse(messageText);

      if (message.type === 'updateCoordinates') {
        winston.debug('Courier ' + courier.id + ', state = ' + courier.state + ' updating position in Redis...');
        Courier.updateCoordinates(courier, message.coordinates);
      }

    });

    ws.on('close', function() {
      console.log('Courier #' + courier.id + ' disconnected!');
      Courier.Pool.remove(courier);
      console.log('Number of couriers connected: ' + Courier.Pool.size());
    });
});

// Handle restarts
process.on('SIGINT', function () {
  console.log('---------------------');
  console.log('- STOPPING DISPATCH -');
  console.log('---------------------');
  isClosing = true;
  deliveryDispatcher.stop();
  Courier.Pool.removeAll();
});
