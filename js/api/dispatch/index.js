var WebSocketServer = require('ws').Server;
var http = require('http');
var fs = require('fs');
var YAML = require('js-yaml');
var Sequelize = require('sequelize');

var winston = require('winston');
winston.level = process.env.NODE_ENV === 'production' ? 'info' : 'debug';

var ROOT_DIR = __dirname + '/../../..';
var CONFIG = {};

console.log('---------------------');
console.log('- STARTING DISPATCH -');
console.log('---------------------');

try {
  var yaml = YAML.safeLoad(fs.readFileSync(ROOT_DIR + '/app/config/parameters.yml', 'utf8'));
  CONFIG = yaml.parameters;
} catch (e) {
  console.log(e);
}

var cert = fs.readFileSync(ROOT_DIR + '/var/jwt/public.pem');

var redis = require('redis').createClient();
var redisPubSub = require('redis').createClient();

var sequelize = new Sequelize(CONFIG.database_name, CONFIG.database_user, CONFIG.database_password, {
  host: CONFIG.database_host,
  dialect: 'postgres',
  logging: false,
});

var server = http.createServer(function(request, response) {
    // process HTTP request. Since we're writing just WebSockets server
    // we don't have to implement anything.
});
server.listen(8000, function() {});

var Db = require('../Db')(sequelize);

var Courier = require('../models/Courier').Courier;
Courier.init(redis, redisPubSub);

var Order = require('../models/Order').Order;
Order.init(redis, sequelize, Db);

var OrderDispatcher = require('../models/OrderDispatcher');
var orderDispatcher = new OrderDispatcher(redis, Order.Registry);

var TokenVerifier = require('../TokenVerifier');
var tokenVerifier = new TokenVerifier(cert, Db);

/* Order dispatch loop */

orderDispatcher.setHandler(function(order, next) {

  winston.info('Trying to dispatch order #' + order.id);

  Courier.nearestForOrder(order, 3000).then(function(courier) {

    if (!courier) {
      winston.debug('No couriers nearby');
      return next();
    }

    console.log('Dispatching order #' + order.id + ' to courier #' + courier.id);

    // There is a courier available
    // Change state to "DISPATCHING" and wait for feedback
    courier.setOrder(order.id);
    courier.setState(Courier.DISPATCHING);

    // Remove order from the waiting list
    redis.lrem('orders:waiting', 0, order.id, function(err) {
      if (err) throw err;
      redis.lpush('orders:dispatching', order.id, function(err) {
        if (err) throw err;
        // TODO record dispatch event ?
        courier.send({
          type: 'order',
          order: {
            id: order.id,
            restaurant: order.restaurant.position,
            deliveryAddress: order.delivery_address.position
          }
        });
        next();
      });
    });
  });
});

// Load orders in Redis
Order.load().then(function() {
  console.log('Everything is loaded, starting dispatch loop...');
  orderDispatcher.start();
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
  orderDispatcher.stop();
  Courier.Pool.removeAll();
});
