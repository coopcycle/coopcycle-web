require('dotenv').config()

var serialize = require('locutus/php/var/serialize');
var pg = require('pg');
var fs = require('fs');
var net = require('net');
var DatabaseCleaner = require('database-cleaner');
var jwt = require('jsonwebtoken');
var Sequelize = require('sequelize');
var _ = require('lodash');
var pgCleaner = new DatabaseCleaner('postgresql', {
  postgresql: {
    skipTables: [],
    strategy: 'truncation'
  }
});
var redisCleaner = new DatabaseCleaner('redis');

function TestUtils() {

  let port = 5432
  if (process.env.COOPCYCLE_DB_PORT) {
    port = parseInt(process.env.COOPCYCLE_DB_PORT, 10)
  }

  this.pgConfig = {
    user: process.env.COOPCYCLE_DB_USER,
    database: process.env.COOPCYCLE_DB_NAME + '_test',
    password: process.env.COOPCYCLE_DB_PASSWORD || null,
    host: process.env.COOPCYCLE_DB_HOST,
  };

  this.redis = require('redis').createClient({
    prefix: process.env.COOPCYCLE_DB_NAME + '_test:',
    url: process.env.COOPCYCLE_REDIS_DSN
  });

  this.tile38 = require('redis').createClient({
    url: process.env.COOPCYCLE_TILE38_DSN
  });

  var privateKey = fs.readFileSync(__dirname + '/../../var/jwt/private.pem');

  this.cert = {
    key: privateKey,
    passphrase: process.env.COOPCYCLE_PRIVATE_KEY_PASSPHRASE
  };

  var sequelize = new Sequelize(
    process.env.COOPCYCLE_DB_NAME + '_test',
    process.env.COOPCYCLE_DB_USER,
    process.env.COOPCYCLE_DB_PASSWORD || null,
    {
      host: process.env.COOPCYCLE_DB_HOST,
      port: port,
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
  var tile38 = this.tile38;

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

      var cleanTile38 = new Promise(function(resolve, reject) {
        const tile38FleetKey = `${process.env.COOPCYCLE_DB_NAME}_test:fleet`
        tile38.send_command('DROP', [tile38FleetKey], function(err) {
          if (!err) {
            resolve()
          } else {
            reject()
          }
        })
      });

      return Promise.all([ cleanPg, cleanRedis, cleanTile38 ])
        .then(() => resolve())
        .catch(err => reject(err))
    });
  });
};

TestUtils.prototype.createUser = function(username, roles) {

  const { Customer, User } = this.db

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

    Customer.create({
      email: username + '@coopcycle.dev',
      email_canonical: username + '@coopcycle.dev',
      createdAt: new Date(),
      updatedAt: new Date(),
    })
    .then(customer => {
      const user = User.build({
        ...params,
        customerId: customer.id,
      })
      user.save()
        .then(u => resolve(u))
        .catch(function(e) {
          reject(e)
        })

    })
    .catch(function(e) {
      reject(e)
    })
  })
};

TestUtils.prototype.createRestaurant = function(name, coordinates) {
  var Restaurant = this.db.Restaurant;
  var Address = this.db.Address;
  var Organization = this.db.Organization;

  return new Promise(function (resolve, reject) {

    Address.create({
      postalCode: '991',
      streetAddress: 'testStreet',
      addressLocality: 'Paris big city of the dream',
      geo: { type: 'Point', coordinates: [ coordinates.latitude, coordinates.longitude ] }
    }).then(function(address) {

      Organization.create({
        name: 'Acme'
      }).then(function(organization) {
        Restaurant.build({
          type: 'restaurant',
          name: name,
          createdAt: new Date(),
          updatedAt: new Date(),
          organizationId: organization.id,
        })
        .save()
        .then(function(restaurant) {
          restaurant.setAddress(address).then(function() {
            resolve(restaurant);
          })
        })
      })
    })

  });
};

TestUtils.prototype.updateLocation = function(username, latitude, longitude) {
  const tile38FleetKey = `${process.env.COOPCYCLE_DB_NAME}_test:fleet`

  return new Promise((resolve, reject) => {
    this.tile38.send_command('SET', [tile38FleetKey, username,
      'POINT', latitude, longitude], function(err, res) {
      if (!err) {
        resolve()
      }
    })
  });
};

let timeoutId;

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
