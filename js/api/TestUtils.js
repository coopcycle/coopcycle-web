var Promise = require('promise');
var exec = require('child_process').exec;
var serialize = require('locutus/php/var/serialize');
var pg = require('pg');
var fs = require('fs');
var DatabaseCleaner = require('database-cleaner');
var jwt = require('jsonwebtoken');
var Sequelize = require('sequelize');
var _ = require('underscore');

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
  var privateKeyPath = jwtConfig.private_key_path.replace('%kernel.root_dir%', rootDir + '/app');
  var privateKey = fs.readFileSync(privateKeyPath);

  this.cert = {
    key: privateKey,
    passphrase: jwtConfig.pass_phrase
  }

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

  this.db = require('./Db')(sequelize);
}

TestUtils.prototype.createJWT = function(username) {
  return jwt.sign({ username: username }, this.cert, { algorithm: 'RS256' });
}

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
}

TestUtils.prototype.createUser = function(username, roles) {
  var pgConfig = this.pgConfig;
  return new Promise(function (resolve, reject) {

    var params = [
      username,
      username + '@coopcycle.dev',
      '123456'
    ]

    var command = 'php ' + rootDir + '/bin/console'
      + ' --env=test fos:user:create ' + params.join(' ');

    // Execute Symfony command to create user
    exec(command, function(error, stdout, stderr) {
      if (error) return reject(error);
      if (roles && Array.isArray(roles)) {
        pg.connect(pgConfig, function(err, client, release) {
          var sql = 'UPDATE api_user SET roles = $1 WHERE username = $2'
          client.query(sql, [serialize(roles), username], function (err, result) {
            if (err) throw err;
            release();
            resolve();
          });
        });
      } else {
        resolve();
      }
    });
  });
}

TestUtils.prototype.createDeliveryAddress = function(username, streetAddress, geo) {

  var DeliveryAddress = this.db.DeliveryAddress;
  var Customer = this.db.Customer;

  return new Promise(function (resolve, reject) {
    Customer.findOne({ where: { username: username } })
      .then((customer) => {
        DeliveryAddress.create({
          id: 1, // FIXME Postgres SEQUENCE does not work
          streetAddress: streetAddress,
          geo: { type: 'Point', coordinates: [ geo.lat, geo.lng ]}
        })
        .then(function(deliveryAddress) {
          deliveryAddress.setCustomer(customer).then(function() {
            resolve();
          })
        })
        .catch(function(err) {
          reject(err.message)
        });
      });
  });
}

TestUtils.prototype.createRestaurant = function(name, coordinates) {
  var Restaurant = this.db.Restaurant;

  return Restaurant.create({
    id: 1, // FIXME Postgres SEQUENCE does not work
    name: name,
    geo: { type: 'Point', coordinates: [ coordinates.latitude, coordinates.longitude ] }
  });
}

TestUtils.prototype.createRandomOrder = function(username, restaurant) {

  var Restaurant = this.db.Restaurant;
  var Order = this.db.Order;
  var Customer = this.db.Customer;
  var redis = this.redis;

  return new Promise(function (resolve, reject) {

    Customer.findOne({ where: { username: username } })
      .then(function(customer) {
        customer.getDeliveryAddresses()
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
                  id: 1,
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
                  return order.setDeliveryAddress(deliveryAddress);
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

}

// Check Redis version on Travis, to skip tests using geo commands
// Code borrowed from https://github.com/NodeRedis/node_redis
TestUtils.prototype.serverVersionAtLeast = function (connection, desired_version) {

  // Wait until a connection has established (otherwise a timeout is going to be triggered at some point)
  if (Object.keys(connection.server_info).length === 0) {
      throw new Error('Version check not possible as the client is not yet ready or did not expose the version');
  }

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