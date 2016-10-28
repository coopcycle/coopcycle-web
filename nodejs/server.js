var WebSocketServer = require('websocket').server;
var http = require('http');
var fs = require('fs');
var jwt = require('jsonwebtoken');
var _ = require('underscore');

var server = http.createServer(function(request, response) {
    // process HTTP request. Since we're writing just WebSockets server
    // we don't have to implement anything.
});
server.listen(8000, function() {});

// create the server
wsServer = new WebSocketServer({
    httpServer: server
});

var cert = fs.readFileSync(__dirname + '/../var/jwt/public.pem');
var redis = require('redis').createClient();

var connectionIDCounter = 0;
var connections = [];
var pubSubClients = [];

// WebSocket server
wsServer.on('request', function(request) {

    var connection = request.accept(null, request.origin);
    var pubSubClient = require('redis').createClient();
    var username;
    var courierID;
    var timeoutID;

    // Store a reference to the connection using an incrementing ID
    connection.id = connectionIDCounter++;

    connections.push(connection);
    pubSubClients.push(pubSubClient);

    console.log('New connection with id = ' + connection.id);

    pubSubClient.on("error", function (err) {
      console.log("Redis error " + err);
    });

    // TODO Use setTimeout
    // var timeoutID = setTimeout(pollRedis, 500);
    // var intervalID = setInterval(function() {
    //   redis.rpoplpush('Orders', 'Orders', function(err, orderID) {
    //     redis.hgetall('Orders:' + orderID, function(err, order) {
    //       // GEORADIUS locations -0.11759 51.50574 1 km WITHDIST ASC
    //       redis.georadius('GeoSet', order.longitude, order.latitude, 5, "km", function(err, couriers) {
    //         _.each(couriers, function(courier) {
    //           if (courier === username) {
    //             console.log('Found a matching order');
    //           }
    //         });
    //       });
    //     })
    //   })
    // }, 2500);

    // connection.intervalID = intervalID;

    // pubSubClient.subscribe("orders");

    // pubSubClient.on("message", function(channel, message) {
    //   console.log("Message '" + message + "' on channel '" + channel + "' arrived!")
    //   connection.sendUTF(JSON.stringify({
    //     channel: channel,
    //     message: JSON.parse(message)
    //   }));
    // });

    // Polls Redis to look for orders nearby the courier
    var pollRedis = function(courierID, callback) {
      redis.georadiusbymember('GeoSet', 'courier:' + courierID, 5, "km", 'WITHDIST', function(err, matches) {
        var orders = _.reject(matches, function(match) {
          return match[0] === 'courier:' + courierID;
        });
        // An order has been found : notify courier, stop polling
        if (orders.length > 0) {
          var order = _.first(orders);
          var orderKey = order[0];
          var orderID = parseInt(orderKey.substring('order:'.length), 10);
          redis.geopos('GeoSet', orderKey, function(err, results) {
            var latitude = results[0][0];
            var longitude = results[0][1];
            callback({
              id: orderID,
              longitude: parseFloat(longitude),
              latitude: parseFloat(latitude),
            });
            clearTimeout(timeoutID);
          })
        }
      });
      timeoutID = setTimeout(pollRedis.bind(null, courierID, callback), 1000);
    }

    var onOrderFound = function(order) {
      console.log(order);
      connection.sendUTF(JSON.stringify(order));
    }

    connection.on('message', function(message) {
      if (message.type === 'utf8') {
        message = JSON.parse(message.utf8Data);
        if (message.type === 'updateCoordinates') {
          jwt.verify(message.token, cert, function(err, user) {
            if (!err) {
              username = user.username;
              courierID = message.user.id;
              connection.courierID = courierID;
              console.log('Updating position for courier "' + courierID + '" in Redis...');
              redis.geoadd('GeoSet', message.coordinates.latitude, message.coordinates.longitude, 'courier:' + courierID);
              if (!timeoutID) {
                console.log('Start polling Redis for courier ' + courierID);
                timeoutID = setTimeout(pollRedis.bind(null, courierID, onOrderFound), 500);
              }
            } else {
              console.log('Could not verify token', err);
            }
          });
        }
      }
    });

    connection.on('close', function() {

      console.log('Connection ' + connection.id + ' closed !');
      clearTimeout(timeoutID);

      console.log('Removing courier "' + courierID + '" from Redis...');
      pubSubClient.unsubscribe("orders");
      redis.zrem('GeoSet', 'courier:' + courierID);

      var connectionsIndex = connections.indexOf(connection);
      var pubSubClientsIndex = pubSubClients.indexOf(pubSubClient);
      if (-1 !== connectionsIndex) {
        connections.splice(connectionsIndex, 1);
      }
      if (-1 !== pubSubClientsIndex) {
        pubSubClients.splice(pubSubClientsIndex, 1);
      }

      // clearInterval(connection.intervalID);

      console.log('Number of connections : ' + _.size(connections));
    });
});

process.on('SIGINT', function () {
  _.each(connections, function(connection) {
    if (connection) {
      console.log('Removing courier "' + connection.courierID + '" from Redis...');
      redis.zrem('GeoSet', 'courier:' + connection.courierID);
      console.log('Unsubscribing connection "' + connection.id + '" from Redis...');
      pubSubClients[connection.id].unsubscribe("orders");
    }
  });
});