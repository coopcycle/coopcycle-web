var serialize = require('locutus/php/var/serialize');
var pg = require('pg');
var fs = require('fs');
var net = require('net');
var DatabaseCleaner = require('database-cleaner');
var jwt = require('jsonwebtoken');
var Sequelize = require('sequelize');
var _ = require('underscore');


var pgCleaner = new DatabaseCleaner('postgresql', {
  postgresql: {
    skipTables: [],
    strategy: 'truncation'
  }
});
var redisCleaner = new DatabaseCleaner('redis');

function TestUtils(config) {

  this.config = config;

  this.pgConfig = {
    user: config.doctrine.dbal.user,
    database: config.doctrine.dbal.dbname,
    password: config.doctrine.dbal.password,
    host: config.doctrine.dbal.host,
  };

  this.redis = require('redis').createClient({
    url: config.snc_redis.clients.default.dsn
  });

  var jwtConfig = config.lexik_jwt_authentication;
  var privateKey = fs.readFileSync(jwtConfig.private_key_path);

  this.cert = {
    key: privateKey,
    passphrase: jwtConfig.pass_phrase
  };

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

  this.db = require('../api/Db')(sequelize);
}

TestUtils.prototype.createJWT = function(username) {
  return jwt.sign({ username: username }, this.cert, { algorithm: 'RS256' });
};

TestUtils.prototype.cleanDb = function() {
  var pgConfig = this.pgConfig;
  var redis = this.redis;

  var pool = new pg.Pool(pgConfig)

  return new Promise(function(resolve, reject) {
    pool.connect(function(err, client, done) {

      if (err) return reject(err);

      var cleanRedis = new Promise(function(resolve, reject) {
        redisCleaner.clean(redis, function(err) {
          if (err) reject(err);
          resolve();
        });
      });

      var cleanPg = new Promise(function(resolve, reject) {
        pgCleaner.clean(client, function(err) {
          if (err) reject(err);
          pool.end();
          resolve();
        });
      });

      Promise.all([ cleanPg, cleanRedis ])
        .then(resolve)
        .catch(function(err) {
          reject(err);
        });
    });
  });
};

TestUtils.prototype.createUser = function(username, roles) {
  var User = this.db.User;

  var params = {
    'username': username,
    'given_name': username,
    'family_name': username,
    'telephone': '00000',
    'username_canonical': username,
    'email': username + '@coopcycle.dev',
    'email_canonical': username + '@coopcycle.dev',
    'roles': serialize(roles),
    'password': '123456',
    'enabled': true
  };

  return User.create(params);
};

TestUtils.prototype.createDeliveryAddress = function(username, streetAddress, geo) {

    var Address = this.db.Address;
    var User = this.db.User;

    return new Promise(function (resolve, reject) {
        var customer = User.findOne({where: {username: username}});
        var address = Address.create({streetAddress: streetAddress, geo: {type: 'Point', coordinates: [geo.lat, geo.lng]}});
        Promise.all([customer, address])
            .then(function (results) {
                return results[0].addAddress(results[1]);
            })
            .then(resolve)
            .catch(function (err) {
                var error = err.errors ? err.errors : err;
                reject(error);
            });
    });
}


TestUtils.prototype.createRestaurant = function(name, coordinates) {
  var Restaurant = this.db.Restaurant;
  var Address = this.db.Address;

  return new Promise(function (resolve, reject) {

    Address.create({
      geo: { type: 'Point', coordinates: [ coordinates.latitude, coordinates.longitude ] }
    }).then(function(address) {
      Restaurant.create({ name: name })
        .then(function(restaurant) {
          restaurant.setAddress(address).then(function() {
            resolve(restaurant);
          })
        })
    })

  });
}

TestUtils.prototype.createRandomOrder = function(username, restaurant) {

  var Order = this.db.Order;
  var User = this.db.User;
  var Delivery = this.db.Delivery;
  var DeliveryAddress = this.db.DeliveryAddress;
  var redis = this.redis;

  return new Promise(function (resolve, reject) {

    User.findOne({ where: { username: username } })
      .then(function(customer) {
          return customer.getAddresses()
          .then(function(customerAddresses) {
            var customerAddress = _.first(customerAddresses);
            return DeliveryAddress.create({
              streetAddress: customerAddress.streetAddress,
              geo: {type: 'Point',
                    coordinates: [customerAddress.position.latitude, customerAddress.position.longitude]}
            });
          })
          .then(function(deliveryAddress) {
            Order.create({
              createdAt: new Date(),
              updatedAt: new Date(),
            })
            .then(function(order) {
              return order.setCustomer(customer);
            })
            .then(function(order) {
              return order.setRestaurant(restaurant);
            })
            .then(function(order) {

              return Delivery.create({
                date: new Date(),
                distance: 1000,
                duration: 600
              }).then(function(delivery) {
                return restaurant.getAddress().then(function(restaurantAddress) {
                  return delivery.setOriginAddress(restaurantAddress);
                });
              }).then(function(delivery) {
                return delivery.setDeliveryAddress(deliveryAddress);
              }).then(function(delivery) {
                return order.setDelivery(delivery).then(function() {
                  return order;
                });
              }).catch(function(e) {
                reject(e);
              });

            })
            .then(function(order) {
              redis.lpush('orders:waiting', order.id, function(err) {
                if (err) return reject(err);
                resolve(order);
              });
            })
            .catch(function(e) {
              reject(e);
            });
          })
          .catch(function(e) {
            reject(e);
          });
        });
  });

};

// Check Redis version on Travis, to skip tests using geo commands
// Code borrowed from https://github.com/NodeRedis/node_redis
TestUtils.prototype.serverVersionAtLeast = function (connection, desired_version) {

  // Return true if the server version >= desired_version
  var version = connection.server_info.versions;
  for (var i = 0; i < 3; i++) {
      if (version[i] > desired_version[i]) {
          return true;
      }
      if (version[i] < desired_version[i]) {
          if (this.skip) this.skip();
          return false;
      }
  }

  return true;
};

TestUtils.prototype.waitServerUp = function (host, port, timeout) {
  /*
    Wait for the connection to be open at the specified host/port
    - host : server host
    - port : server port
    - timeout : timeout (in milliseconds)
  */

  var timeout = timeout || 50000,
      client;

   function cleanUp() {
       if (client) {
           client.removeAllListeners('connect');
           client.removeAllListeners('error');
           client.end();
           client.destroy();
           client.unref();
       }
     }

  return new Promise(function (resolve, reject) {

    timeoutId = setTimeout(function () {
     reject('Unable to connect to server');
    }, timeout);

    function onConnectCb () {
      // console.log('Server is up!');
      clearTimeout(timeoutId);
      cleanUp();
      resolve();
    }

    function onErrorCb (err) {
      if (err.code === 'ECONNREFUSED') {
        // console.log('Unable to connect retrying..');
        setTimeout(doCheck, 200);
      } else {
        cleanUp();
        reject(err);
      }
    }

    function doCheck() {
      client = new net.Socket();
      client.once('connect', onConnectCb);
      client.once('error', onErrorCb);
      client.connect({port: port, host: host});
    }

    doCheck();
  });
};

module.exports = TestUtils;
