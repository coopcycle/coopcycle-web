var exec = require('child_process').exec;
var serialize = require('locutus/php/var/serialize');
var pg = require('pg');
var fs = require('fs');
var DatabaseCleaner = require('database-cleaner');
var jwt = require('jsonwebtoken');
var Sequelize = require('sequelize');
var _ = require('underscore');
var path = require('path');

var rootDir = fs.realpathSync(__dirname + '/../../');
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

  return new Promise(function(resolve, reject) {
    pg.connect(pgConfig, function(err, client, release) {

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
          release();
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
  var pgConfig = this.pgConfig,
      User = this.db.User;

  var params = {
    'username': username,
    'username_canonical': username,
    'email': username + '@coopcycle.dev',
    'email_canonical': username + '@coopcycle.dev',
    'roles': serialize(roles),
    'password': '123456',
    'enabled': true
  };

  return new Promise(function (resolve, reject) {
    User.create(params)
        .then(function() {
          resolve();
        })
        .catch(function(err) {
          reject(err.message);
        });
});
};

TestUtils.prototype.createDeliveryAddress = function(username, streetAddress, geo) {

  var Address = this.db.Address;
  var User = this.db.User;

  return new Promise(function (resolve, reject) {
    User.findOne({ where: { username: username } })
      .then((customer) => {
        Address.create({
          streetAddress: streetAddress,
          geo: { type: 'Point', coordinates: [ geo.lat, geo.lng ]}
        })
        .then(function(address) {
          customer.addAddress(address).then(resolve);
        })
        .catch(function(err) {
          reject(err.message)
        });
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

  var Restaurant = this.db.Restaurant;
  var Order = this.db.Order;
  var User = this.db.User;
  var Delivery = this.db.Delivery;
  var redis = this.redis;

  return new Promise(function (resolve, reject) {

    User.findOne({ where: { username: username } })
      .then(function(customer) {
        customer.getAddresses()
          .then(function(deliveryAddresses) {
            return _.first(deliveryAddresses);
          })
          .then(function(deliveryAddress) {
            restaurant.getProducts()
              .then(function(products) {
                var numberOfProducts = _.random(2, 5);
                var cart = [];
                if (products.length > 0) {
                  while (cart.length < numberOfProducts) {
                    cart.push(_.first(_.shuffle(products)));
                  }
                }

                return cart;
              })
              .then(function(products) {
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

module.exports = TestUtils;
