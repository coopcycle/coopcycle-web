var assert = require('assert');
var fs = require('fs');
var io = require('socket.io-client');

var ConfigLoader = require('../api/ConfigLoader');
var TestUtils = require('./utils');

var configLoader = new ConfigLoader('app/config/config_test.yml');
var config = configLoader.load();

var utils = new TestUtils(config);

var pub = require('../api/RedisClient')({
  prefix: config.snc_redis.clients.default.options.prefix,
  url: config.snc_redis.clients.default.dsn
});

var initUsers = function() {
  return new Promise(function(resolve, reject) {
    Promise.all([
      utils.createUser('bill',  ['ROLE_USER']),
      utils.createUser('sarah', ['ROLE_USER', 'ROLE_ADMIN']),
      utils.createUser('bob',   ['ROLE_USER', 'ROLE_RESTAURANT']),
      utils.createUser('wendy', ['ROLE_USER']),
    ])
    .then(function(users) {
      const [ bill, sarah, bob ] = users
      utils
        .createRestaurant('foo', { latitude: 48.856613, longitude: 2.352222 })
        .then(restaurant => {
          bob.addRestaurant(restaurant).then(() => {
            resolve()
          })
        })
    })
    .catch(function(e) {
      reject(e)
    })
  })
}

function createSocket(username) {
  return io.connect('http://127.0.0.1:8001', {
    path: '/tracking/socket.io',
    forceNew: true,
    transports: ['websocket'],
    query: {
      token: utils.createJWT(username),
    }
  })
}

describe('Connect to Socket.IO', function() {

  before('Waiting for server', function() {
    this.timeout(30000)
    return new Promise(function (resolve, reject) {
      utils.waitServerUp('127.0.0.1', 8001).then(function() {
        resolve()
      })
    })
  });

  beforeEach('Cleaning db & initializing users', function() {
    this.timeout(30000)
    return new Promise(function (resolve, reject) {
      utils.cleanDb()
        .then(function() {
          initUsers().then(function() {
            resolve()
          })
        })
    })
  });

  it('should return authentication error without JWT', function() {
    return new Promise((resolve, reject) => {

      var socket = io.connect('http://127.0.0.1:8001', {
        path: '/tracking/socket.io',
        forceNew: true,
        transports: ['websocket'],
      });

      socket.on('error', (error) => {
        assert.equal('Authentication error', error);
        resolve();
      });

    })
  });

  it('should connect successfully with valid JWT', function() {
    return new Promise((resolve, reject) => {
      var socket = createSocket('bill');
      socket.on('connect', function() {
        resolve();
      })
    })
  });

  [
    'order:accepted',
    'order:picked',
    'order:dropped',
    'order:fulfilled'
  ].forEach((eventName) => {
    it(`should emit "${eventName}" message to expected users`, function() {
      return new Promise((resolve, reject) => {

        const socketForBill = createSocket('bill')
        const socketForSarah = createSocket('sarah')

        const data = {
          restaurant: {
            id: 1
          },
        }

        const message = {
          name: eventName,
          data
        }

        // Wait for all sockets to connect, and send message
        Promise.all([
          new Promise((resolve, reject) => socketForBill.on('connect', () => resolve())),
          new Promise((resolve, reject) => socketForSarah.on('connect', () => resolve())),
        ]).then(() => {
          pub.prefixedPublish('users:bill', JSON.stringify(message))
        })

        socketForBill.on(eventName, function(message) {
          assert.deepEqual(data, message);
          resolve();
        })

        socketForSarah.on(eventName, function(message) {
          reject(new Error(`Message "${eventName}" should not have been emitted`));
        })

      })
    });
  });

});
