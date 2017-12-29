var assert = require('assert');
var fs = require('fs');
var WebSocket = require('ws');

var ConfigLoader = require('../api/ConfigLoader');
var TestUtils = require('./utils');

var configLoader = new ConfigLoader('app/config/config_test.yml');
var config = configLoader.load();

var utils = new TestUtils(config);

function init() {

  return new Promise(function(resolve, reject) {

    Promise.all([
      utils.createRestaurant('Awesome Pizza', { latitude: 48.884550, longitude: 2.341358 }),
      utils.createTaxCategory('Default', 'default'),
      utils.createUser('bill'),
      utils.createUser('sarah', ['ROLE_COURIER']),
      utils.createUser('bob', ['ROLE_COURIER']),
    ])
    .then(function(objects) {
      utils.createDeliveryAddress('bill', '1, rue de Rivoli', {
        lat: 48.855799,
        lng: 2.359207
      })
      .then(function() {
        resolve(objects)
      })
    })
    .catch(function(e) {
      reject(e)
    })

  })

}

describe('Dispatch WebSocket', function() {

  before('Waiting for server', function() {
    this.timeout(30000)
    return new Promise(function (resolve, reject) {
      utils.waitServerUp('127.0.0.1', 8000)
        .then(function() {
          resolve()
        })
        .catch(function(e) {
          reject()
        })
    })
  })

  let restaurant;

  beforeEach('Cleaning db & initializing users', function() {
    this.timeout(30000)
    return new Promise(function (resolve, reject) {
      utils.cleanDb().then(function() {
        init().then(function(objects) {

          const [ newRestaurant ] = objects

          restaurant = newRestaurant

        }).then(resolve).catch(reject)
      })
    })
  });

  function createWebSocket(username) {

    var ws = new WebSocket('http://localhost:8000', {
      headers: {
        Authorization: 'Bearer ' + utils.createJWT(username)
      }
    });

    ws.on('open', function() {
      assert.equal(WebSocket.OPEN, ws.readyState);
    });

    return ws;
  };

  function send(ws, data, timeout) {
    return new Promise(function (resolve, reject) {
      setTimeout(() => {

        ws.send(JSON.stringify(data), e => {
          if (e) {
            console.error('WebSocket error', e)
          }
          resolve()
        })
      }, timeout)
    })
  }

  it('should dispatch order to courier on connection', function() {

    this.timeout(10000)

    return new Promise(function (resolve, reject) {

      var sarah = createWebSocket('sarah');

      sarah.on('message', function(message) {

        var data = JSON.parse(message);
        assert.equal('delivery', data.type);

        sarah.close();
        resolve();
      });

      sarah.on('error', function(e) {
        reject(e.message);
      });

      utils.createRandomOrder('bill', restaurant, 'default')
        .then(() => {
          send(sarah, {
            type: "updateCoordinates",
            coordinates: { latitude: 48.883083, longitude: 2.344276 }
          }, 2000)
        })

    });
  })

  it('should dispatch order to closest courier', function() {

    this.timeout(10000);

    return new Promise(function (resolve, reject) {

      var sarah = createWebSocket('sarah');
      var bob = createWebSocket('bob');

      sarah.onerror = bob.onerror = function(e) {
        reject(e.message);
      };

      sarah.on('message', function(message) {

        var data = JSON.parse(message);
        assert.equal('delivery', data.type);

        sarah.close();
        bob.close();
        resolve();
      });

      bob.on('message', function(message) {
        sarah.close();
        bob.close();
        reject('Farest courier should not receive order');
      });

      const messages = [
        send(sarah, {
          type: "updateCoordinates",
          coordinates: { latitude: 48.883083, longitude: 2.344276 }
        }, 2000),
        send(bob, {
          type: "updateCoordinates",
          coordinates: { latitude: 48.86069, longitude: 2.35525 }
        }, 2000)
      ]

      Promise.all(messages)
        .then(() => utils.createRandomOrder('bill', restaurant, 'default'))

    })

  })

  it('should dispatch order to closest courier (one is at the exact same place as the restaurant)', function() {

    this.timeout(10000);

    return new Promise(function (resolve, reject) {

      var sarah = createWebSocket('sarah');
      var bob = createWebSocket('bob');

      sarah.onerror = bob.onerror = function(e) {
        reject(e.message);
      };

      sarah.on('message', function(message) {

        var data = JSON.parse(message);
        assert.equal('delivery', data.type);

        sarah.close();
        bob.close();
        resolve();
      });

      bob.on('message', function(e) {
        sarah.close();
        bob.close();
        reject('Farest courier should not receive order');
      });

      const messages = [
        send(sarah, {
          type: "updateCoordinates",
          coordinates: { latitude: 48.884550, longitude: 2.341358 }
        }, 2000),
        send(bob, {
          type: "updateCoordinates",
          coordinates: { latitude: 48.86069, longitude: 2.35525 }
        }, 2000)
      ]

      Promise.all(messages)
        .then(() => utils.createRandomOrder('bill', restaurant, 'default'))
    })

  })

})
