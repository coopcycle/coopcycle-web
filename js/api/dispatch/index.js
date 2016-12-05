var WebSocketServer = require('ws').Server;
var http = require('http');
var fs = require('fs');
var jwt = require('jsonwebtoken');
var pg = require('pg');
var _ = require('underscore');
var Promise = require('promise');

var cert = fs.readFileSync(__dirname + '/../../../var/jwt/public.pem');
var pgPool = new pg.Pool({
  user: 'postgres',
  database: 'coursiers',
  password: ''
});
var redis = require('redis').createClient();

var server = http.createServer(function(request, response) {
    // process HTTP request. Since we're writing just WebSockets server
    // we don't have to implement anything.
});
server.listen(8000, function() {});


var CourierUtils = require('../CourierUtils');
var courierUtils = new CourierUtils(redis);

var connections = [];

function nextOrder() {
  rotateListTimeout = setTimeout(rotateList, 1000);
}

function findCourier(courierID) {
  return _.find(connections, function(courier) {
    return courier.id === courierID;
  });
}

function getOrderCoords(orderID) {
  return new Promise(function(resolve, reject) {
    redis.geopos('GeoSet', 'order:' + orderID, function(err, results) {
      resolve({
        latitude: parseFloat(results[0][0]),
        longitude: parseFloat(results[0][1])
      })
    });
  });
}

// ---

var rotateListTimeout;
var rotateList = function() {
  redis.rpoplpush('Orders', 'Orders', function(err, orderID) {

    if (!orderID) {
      console.log('No orders to process yet');
      return nextOrder();
    }

    console.log('Looking for courier for order #' + orderID);
    courierUtils.nearestCouriers(orderID).then(function(results) {
      if (results.length === 0) {
        console.log('No couriers nearby');
        return nextOrder();
      }

      var couriers = _.map(results, function(result) {
        var id = parseInt(result[0].substring('courier:'.length), 10);
        var courier = findCourier(id);
        courier.status = courier.status || 'AVAILABLE';
        return courier;
      });

      var available = _.filter(couriers, function(courier) {
        return courier.status === 'AVAILABLE';
      });

      if (available.length === 0) {
        console.log('No couriers available');
        return nextOrder();
      }

      var courier = _.first(available);

      courier.status = 'BUSY';

      console.log('Removing order #' + orderID + ' from list...');
      redis.lrem('Orders', 1, orderID, function(err) {
        if (!err) {
          getOrderCoords(orderID).then(function(coords) {
            var order = _.extend({id: orderID}, coords);
            courier.ws.send(JSON.stringify({
              type: 'order',
              order: order
            }));
            nextOrder();
          });
        }
      });
    });

  });
}
rotateListTimeout = setTimeout(rotateList, 1000);

// create the server
wsServer = new WebSocketServer({
    server: server,
    verifyClient: function (info, cb) {

      var token = info.req.headers.authorization;
      if (!token) {
        console.log('No JWT found in request');
        return cb(false, 401, 'Unauthorized');
      }

      token = token.substring('Bearer '.length);

      jwt.verify(token, cert, function (err, decoded) {
        if (err) {
          console.log('Invalid JWT', err);
          cb(false, 401, 'Unauthorized');
        } else {
          console.log('JWT verified successfully', decoded);
          // Token is verified, load user from database
          pgPool.connect(function (err, client, done) {
            if (err) throw err;
            console.log('Decoded token', decoded);
            client.query('SELECT id, username FROM api_user WHERE username = $1', [decoded.username], function (err, result) {
              done();
              info.req.user = result.rows[0];
              cb(true);
            });
          });
        }
      });

    }
});

// WebSocket server
wsServer.on('connection', function(ws) {

    var user = ws.upgradeReq.user;

    console.log('User #'+user.id+' connected!');

    var courier = _.extend(user, {ws: ws});

    connections.push(courier);

    ws.on('message', function(messageText) {

      var message = JSON.parse(messageText);

      if (message.type === 'updateCoordinates') {
        console.log('Courier ' + courier.id + ', updating position in Redis...');
        redis.geoadd('GeoSet',
          message.coordinates.latitude,
          message.coordinates.longitude,
          'courier:' + courier.id
        );
      }

    });

    ws.on('close', function() {

      console.log('User #' + courier.id + ' disconnected !');

      console.log('Removing courier "' + courier.id + '" from Redis...');
      redis.zrem('GeoSet', 'courier:' + courier.id);

      var connectionsIndex = connections.indexOf(courier);
      if (-1 !== connectionsIndex) {
        connections.splice(connectionsIndex, 1);
      }

      console.log('Number of connections : ' + _.size(connections));
    });
});

process.on('SIGINT', function () {
  _.each(connections, function(connection) {
    if (connection) {
      console.log('Removing courier #' + connection.id + ' from Redis...');
      redis.zrem('GeoSet', 'courier:' + connection.id);
    }
  });
});