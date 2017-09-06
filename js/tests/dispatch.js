var assert = require('assert');
var fs = require('fs');
var WebSocket = require('ws');

var ConfigLoader = require('../api/ConfigLoader');
var TestUtils = require('./utils');

var configLoader = new ConfigLoader('app/config/config_test.yml');
var config = configLoader.load();

var utils = new TestUtils(config);

var redisVersionCheck = new Promise(function (resolve, reject) {
  var checkRedis = function () {
    if (utils.serverVersionAtLeast(utils.redis, [3, 2, 0])) {
      resolve();
    } else {
      reject('Redis version nok');
    }
  };
  if (!utils.redis.ready) {
    utils.redis.on('ready', function () {
      checkRedis();
    });
  } else {
    checkRedis();
  }
  });

function init() {
  return new Promise(function(resolve, reject) {
    utils.waitServerUp('127.0.0.1', 8000)
         .then(function () {
            return utils.cleanDb();
          })
         .then(function() {
            return Promise.all([
                utils.createUser('bill'),
                utils.createUser('sarah', ['ROLE_COURIER']),
                utils.createUser('bob', ['ROLE_COURIER'])
              ]);
          })
          .then(function() {
            return utils.createDeliveryAddress('bill', '1, rue de Rivoli', {
              lat: 48.855799,
              lng: 2.359207
            });
          })
          .then(resolve)
          .catch((e) => reject(e));
  });
}

describe('With one order waiting', function() {

  before(function() {

    this.timeout(60000);

    return new Promise(function (resolve, reject) {
        redisVersionCheck
          .then(function () {
            return init();
          })
          .then(function() {
            return utils.createRestaurant('Awesome Pizza', { latitude: 48.884550, longitude: 2.341358 });
          })
          .then(function(restaurant) {
            return utils.createRandomOrder('bill', restaurant);
          })
          .then(resolve)
          .catch(function(e) {
              reject(e);
          });
    });
  });

  it('order should be dispatched to courier', function() {

    this.timeout(30000);

    return new Promise(function (resolve, reject) {
      var token = utils.createJWT('sarah');

      var ws = new WebSocket('http://localhost:8000', {
        headers: {
          Authorization: 'Bearer ' + token
        }
      });

      ws.onopen = function() {

        assert.equal(WebSocket.OPEN, ws.readyState);

        var msg = JSON.stringify({
          type: "updateCoordinates",
          coordinates: { latitude: 48.883083, longitude: 2.344276 }
        });
        ws.send(msg);
      };

      ws.onmessage = function(e) {

        assert.equal('message', e.type);

        var data = JSON.parse(e.data);
        assert.equal('delivery', data.type);

        ws.close();
        resolve();
      };

      ws.onerror = function(e) {
        reject(e.message);
      };
    });
  });
});

describe('With several users connected', function() {

  before(function() {

    this.timeout(30000);

    return new Promise(function (resolve, reject) {
      redisVersionCheck
        .then(function () {
          return init();
        })
        .then(resolve)
        .catch(function(e) {
            reject(e);
        });
    });
  });

  it('new order should be dispatched to closest courier', function() {

    this.timeout(5000);

    var createWebSocket = function(username, coordinates) {

      var ws = new WebSocket('http://localhost:8000', {
        headers: {
          Authorization: 'Bearer ' + utils.createJWT(username)
        }
      });

      ws.onopen = function() {
        assert.equal(WebSocket.OPEN, ws.readyState);

        ws.send(JSON.stringify({
          type: "updateCoordinates",
          coordinates: coordinates
        }));
      };

      return ws;
    };

    return new Promise(function (resolve, reject) {

      var sarah = createWebSocket('sarah', { latitude: 48.883083, longitude: 2.344276 });
      var bob = createWebSocket('bob', { latitude: 48.884053, longitude: 2.333172 });

      sarah.onerror = bob.onerror = function(e) {
        reject(e.message);
      };

      sarah.onmessage = function(e) {
        assert.equal('message', e.type);

        var data = JSON.parse(e.data);
        assert.equal('delivery', data.type);

        sarah.close();
        bob.close();
        resolve();
      };

      bob.onmessage = function(e) {
        assert.equal('message', e.type);

        var data = JSON.parse(e.data);
        if ('order' === data.type) {
          sarah.close();
          bob.close();
          reject('Farest courier should not receive order');
        }
      };

      utils.createRestaurant('Awesome Pizza', {
        latitude: 48.884550, longitude: 2.341358
      })
      .then(function(restaurant) {
        return utils.createRandomOrder('bill', restaurant);
      });

    });
  });

});
