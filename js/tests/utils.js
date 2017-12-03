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
    prefix: config.snc_redis.clients.default.options.prefix,
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

  var pool = new pg.Pool(pgConfig);

  return new Promise(function(resolve, reject) {
    pool.connect(function(err, client, done) {

      if (err) return reject(err);

      var cleanRedis = new Promise(function(resolve, reject) {
        redisCleaner.clean(redis, function(err) {
          if (err) return reject(err);
          return resolve();
        });
      });

      var cleanPg = new Promise(function(resolve, reject) {
        pgCleaner.clean(client, function(err) {
          if (err) return reject(err);
          pool.end();
          return resolve();
        });
      });

      return Promise.all([ cleanPg, cleanRedis ])
        .then(() => resolve())
        .catch(err => reject(err))
    });
  });
};

TestUtils.prototype.createUser = function(username, roles) {

  const { User } = this.db

  var params = {
    'username': username,
    'username_canonical': username,
    'email': username + '@coopcycle.dev',
    'email_canonical': username + '@coopcycle.dev',
    'roles': serialize(roles),
    'password': '123456',
    'enabled': true
  }

  return new Promise(function (resolve, reject) {
    User.create(params)
      .then(function(user) {
        resolve(user)
      })
      .catch(function(e) {
        reject(err.errors)
      })
  })
};

TestUtils.prototype.createDeliveryAddress = function(username, streetAddress, geo) {

  var Address = this.db.Address;
  var User = this.db.User;

  return new Promise(function (resolve, reject) {
    User.findOne({ where: { username: username } })
      .then((customer) => {
        Address.create({
          postalCode: '991',
          addressLocality: 'Paris big city of the dream',
          streetAddress: streetAddress,
          geo: { type: 'Point', coordinates: [ geo.lat, geo.lng ]}
        })
        .then(function(address) {
          customer.addAddress(address).then(resolve);
        })
        .catch(function(err) {
          reject(err.errors)
        });
      });
  });
};

TestUtils.prototype.createRestaurant = function(name, coordinates) {
  var Restaurant = this.db.Restaurant;
  var Address = this.db.Address;

  return new Promise(function (resolve, reject) {

    Address.create({
      postalCode: '991',
      streetAddress: 'testStreet',
      addressLocality: 'Paris big city of the dream',
      geo: { type: 'Point', coordinates: [ coordinates.latitude, coordinates.longitude ] }
    }).then(function(address) {
      Restaurant.create({
        name: name,
        createdAt: new Date(),
        updatedAt: new Date(),
      }).then(function(restaurant) {
        restaurant.setAddress(address).then(function() {
          resolve(restaurant);
        })
      })
    })

  });
};

TestUtils.prototype.createRandomOrder = function(username, restaurant) {

  var Restaurant = this.db.Restaurant;
  var Order = this.db.Order;
  var User = this.db.User;
  var Delivery = this.db.Delivery;

  var redis = this.redis;

  return new Promise(function (resolve, reject) {

    Promise.all([
      User.findOne({ where: { username: username } }),
      restaurant.getAddress()
    ]).then(objects => {

      const [customer, restaurantAddress] = objects

      customer.getAddresses()
        .then(deliveryAddresses => _.first(deliveryAddresses))
        .then(function(deliveryAddress) {
          Order.create({
            uuid: 'some-random-string',
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

            let delivery = Delivery.build({
              date: new Date(),
              distance: 1000,
              duration: 600,
              price: 3.5,
            })
            delivery.setOriginAddress(restaurantAddress, { save: false })
            delivery.setDeliveryAddress(deliveryAddress, { save: false })

            return delivery.save()
              .then(function(delivery) {
                return order.setDelivery(delivery).then(function() {
                  return [order, delivery];
                });
              })
              .catch(function(e) {
                reject(e);
              })
          })
          .then(function(args) {
            const [order, delivery] = args;
            redis.lpush('deliveries:waiting', delivery.id, function(err) {
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

  });

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
