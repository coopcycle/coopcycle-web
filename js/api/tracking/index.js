var app = require('http').createServer(handler);
var url = require('url') ;
var io = require('socket.io')(app, {path: '/tracking/socket.io'});
var fs = require('fs');
var _ = require('underscore');
var redis = require('redis').createClient();
var Mustache = require('mustache');
var Promise = require('promise');

console.log('---------------------');
console.log('- STARTING TRACKING -');
console.log('---------------------');

app.listen(8001);

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

function addRestaurantCoords(orders) {
  var keys = orders.map(function(order) {
    return order.key;
  });
  return new Promise(function(resolve, reject) {
    if (keys.length === 0) {
      return resolve([]);
    }
    redis.geopos('restaurants:geo', keys, function(err, values) {
      var ordersWithCoords = orders.map(function(order, index) {
        return _.extend(order,  {
          restaurant: {
            lng: parseFloat(values[index][0]),
            lat: parseFloat(values[index][1])
          }
        })
      });
      resolve(ordersWithCoords);
    });
  });
}

function addDeliveryAddressCoords(orders) {
  var keys = orders.map(function(order) {
    return order.key;
  });
  return new Promise(function(resolve, reject) {
    if (keys.length === 0) {
      return resolve([]);
    }
    redis.geopos('delivery_addresses:geo', keys, function(err, values) {
      var ordersWithCoords = orders.map(function(order, index) {
        return _.extend(order,  {
          deliveryAddress: {
            lng: parseFloat(values[index][0]),
            lat: parseFloat(values[index][1])
          }
        })
      });
      resolve(ordersWithCoords);
    });
  });
}

function getOrders() {
  var promises = [
    getOrdersByState('WAITING'),
    getOrdersByState('DISPATCHING'),
    getOrdersByState('DELIVERING'),
  ];

  return Promise.all(promises).then(function(values) {
    var waiting = values[0];
    var dispatching = values[1];
    var delivering = values[2];

    return waiting.concat(dispatching).concat(delivering);
  });
}

function getOrdersByState(state) {
  if (state === 'DELIVERING') {
    return new Promise(function(resolve, reject) {
      redis.hgetall('orders:' + state.toLowerCase(), function(err, hash) {
        if (!hash) {
          return resolve([]);
        }
        var orders = _.map(hash, function(courierKey, orderKey) {
          return {
            key: orderKey,
            state: state,
            courier: courierKey,
          }
        });
        resolve(orders);
      });
    });
  } else {
    return new Promise(function(resolve, reject) {
      redis.lrange('orders:' + state.toLowerCase(), 0, -1, function(err, ids) {
        var orders = ids.map(function(id) {
          return {
            key: 'order:' + id,
            state: state,
          }
        });
        resolve(orders);
      });
    });
  }
}

function updateObjects(socket) {

  console.time("Loading data from Redis");

  getOrders()
    .then(function(orders) {
      return addRestaurantCoords(orders);
    })
    .then(function(orders) {
      return addDeliveryAddressCoords(orders);
    })
    .then(function(orders) {

      redis.zrange('couriers:geo', 0, -1, function(err, keys) {
        redis.geopos('couriers:geo', keys, function(err, values) {

          console.timeEnd("Loading data from Redis");

          var hash = _.object(keys, values);
          var couriers = _.map(hash, function(value, key) {
            return {
              key: key,
              coords: {
                lng: parseFloat(value[0]),
                lat: parseFloat(value[1])
              }
            }
          });

          console.log(orders);

          socket.emit('orders', orders);
          socket.emit('couriers', couriers);

          setTimeout(function() {
            updateObjects(socket);
          }, 1000);

        });
      });

    });

}

io.on('connection', function (socket) {
  updateObjects(socket);
});