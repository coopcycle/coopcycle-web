var app = require('http').createServer(handler);
var io = require('socket.io')(app, {path: '/tracking/socket.io'});
var fs = require('fs');
var path = require('path');
var url = require('url');
var _ = require('underscore');
var Mustache = require('mustache');
var Promise = require('promise');

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

try {
    // load manifest.json in production
    if (process.env.NODE_ENV === 'production') {
      var manifestPath = path.resolve(config.framework.assets.json_manifest_path),
          jsonManifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
    }
} catch (e) {
  throw e;
}

var redis = require('redis').createClient({
  prefix: config.snc_redis.clients.default.options.prefix,
  url: config.snc_redis.clients.default.dsn
});

console.log('---------------------');
console.log('- STARTING TRACKING -');
console.log('---------------------');

console.log('NODE_ENV = ' + process.env.NODE_ENV);
console.log('PORT = ' + process.env.PORT);
console.log('ASSETS URL = ' + process.env.ASSETS_BASE_URL);

app.listen(process.env.PORT || 8001);

function handler(req, res) {
  fs.readFile(__dirname + '/index.html', function (err, data) {
    if (err) {
      res.writeHead(500);
      return res.end('Error loading index.html');
    }

    var params = url.parse(req.url, true).query;

    var output = Mustache.render(data.toString('utf8'), {
      dev: process.env.NODE_ENV === 'development',
      getAssetUrl: function () {
        return function(filePath) {
          if (process.env.NODE_ENV === 'production' && jsonManifest.hasOwnProperty(filePath)) {
            filePath = jsonManifest[filePath];
          }
          var assets_base_url = process.env.ASSETS_BASE_URL || '';
          return url.resolve(assets_base_url, filePath);
        };
      },
      zoom: params.zoom || 13
    });

    res.writeHead(200);
    res.end(output);
  });
}

function addRestaurantCoords(deliveries) {
  var keys = deliveries.map(function(delivery) {
    return delivery.key;
  });
  return new Promise(function(resolve, reject) {
    if (keys.length === 0) {
      return resolve([]);
    }
    redis.geopos('restaurants:geo', keys, function(err, values) {
      var deliveriesWithCoords = deliveries.map(function(delivery, index) {
        return _.extend(delivery,  {
          restaurant: {
            lng: parseFloat(values[index][0]),
            lat: parseFloat(values[index][1])
          }
        });
      });
      resolve(deliveriesWithCoords);
    });
  });
}

function addDeliveryAddressCoords(deliveries) {
  var keys = deliveries.map(function(delivery) {
    return delivery.key;
  });
  return new Promise(function(resolve, reject) {
    if (keys.length === 0) {
      return resolve([]);
    }
    redis.geopos('delivery_addresses:geo', keys, function(err, values) {
      var deliveriesWithCoords = deliveries.map(function(delivery, index) {
        return _.extend(delivery,  {
          deliveryAddress: {
            lng: parseFloat(values[index][0]),
            lat: parseFloat(values[index][1])
          }
        });
      });
      resolve(deliveriesWithCoords);
    });
  });
}

function getDeliveries() {
  var promises = [
    getDeliveriesByState('WAITING'),
    getDeliveriesByState('DISPATCHING'),
    getDeliveriesByState('DELIVERING'),
  ];

  return Promise.all(promises).then(function(values) {
    var waiting = values[0];
    var dispatching = values[1];
    var delivering = values[2];

    return waiting.concat(dispatching).concat(delivering);
  });
}

function getDeliveriesByState(state) {
  if (state === 'DELIVERING') {
    return new Promise(function(resolve, reject) {
      redis.hgetall('deliveries:' + state.toLowerCase(), function(err, hash) {
        if (!hash) {
          return resolve([]);
        }
        var deliveries = _.map(hash, function(courierKey, deliveryKey) {
          return {
            key: deliveryKey,
            state: state,
            courier: courierKey,
          };
        });
        resolve(deliveries);
      });
    });
  } else {
    return new Promise(function(resolve, reject) {
      redis.lrange('deliveries:' + state.toLowerCase(), 0, -1, function(err, ids) {
        var deliveries = ids.map(function(id) {
          return {
            key: 'delivery:' + id,
            state: state,
          };
        });
        resolve(deliveries);
      });
    });
  }
}

function updateObjects() {

  getDeliveries()
    .then(function(deliveries) {
      return addRestaurantCoords(deliveries);
    })
    .then(function(deliveries) {
      return addDeliveryAddressCoords(deliveries);
    })
    .then(function(deliveries) {

      redis.zrange('couriers:geo', 0, -1, function(err, keys) {
        redis.geopos('couriers:geo', keys, function(err, values) {

          var hash = _.object(keys, values);
          var couriers = _.map(hash, function(value, key) {
            return {
              key: key,
              coords: {
                lng: parseFloat(value[0]),
                lat: parseFloat(value[1])
              }
            };
          });

          io.sockets.emit('deliveries', deliveries);
          io.sockets.emit('couriers', couriers);

          setTimeout(updateObjects, 500);
        });
      });

    });

}

var started = false;

io.on('connection', function (socket) {
  if (!started) {
    console.log('A client is connected, start loop...');
    started = true;
    updateObjects();
  }
});
