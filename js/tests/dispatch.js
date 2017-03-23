var assert = require('assert');
var fs = require('fs');
var WebSocket = require('ws');
var Promise = require('promise');

var ConfigLoader = require('../api/ConfigLoader');
var TestUtils = require('../api/TestUtils');

var configLoader = new ConfigLoader('app/config/config_test.yml');
var config = configLoader.load();

var utils = new TestUtils(config);

var baseURL = process.env.COOPCYCLE_BASE_URL || 'http://coopcycle.dev';

function initDb() {
  return new Promise(function(resolve, reject) {
    utils.cleanDb()
      .then(function() {
          Promise.all([
            utils.createUser('bill').then(function() {
              return utils.createDeliveryAddress('bill', '1, rue de Rivoli', {
                lat: 48.855799,
                lng: 2.359207
              });
            }),
            utils.createUser('sarah', ['ROLE_COURIER'])
          ])
          .then(resolve)
          .catch((e) => reject(e));
      })
      .catch((e) => reject(e));
  });
}

describe('Connect to dispatch WebSocket with order waiting', function() {

  before(function() {

    this.timeout(20000);

    // Skip test on if Redis < 3.2 (Travis)
    utils.serverVersionAtLeast.call(this, utils.redis, [3, 2, 0]);

    return new Promise(function(resolve, reject) {
      initDb()
        .then(function() {
          return utils.createRestaurant('Awesome Pizza', { latitude: 48.884550, longitude: 2.341358 });
        })
        .then(function(restaurant) {
          utils.createRandomOrder('bill', restaurant)
        })
        .then(resolve)
        .catch(function(e) {
          reject(e);
        })
    });
  });

  it('should receive a message', function() {

    this.timeout(5000);

    return new Promise(function (resolve, reject) {
      var token = utils.createJWT('sarah');
      var ws = new WebSocket('http://localhost:8000', {
        headers: {
          Authorization: 'Bearer ' + token
        }
      });
      ws.onopen = function() {
        assert.equal(WebSocket.OPEN, ws.readyState);
        ws.send(JSON.stringify({
          type: "updateCoordinates",
          coordinates: { latitude: 48.883083, longitude: 2.344276 }
        }));
      };
      ws.onmessage = function(e) {

        assert.equal('message', e.type);

        var data = JSON.parse(e.data);

        assert.equal('order', data.type);
        assert.equal(1, data.order.id);

        ws.close();
        resolve();
      }
      ws.onerror = function(e) {
        reject(e.message);
      };
      ws.onclose = function() {
        reject('WebSocket closed unexpectedly');
      };
    });
  });
});
