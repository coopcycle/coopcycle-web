var assert = require('assert');
var fs = require('fs');
var WebSocket = require('ws');
var Promise = require('promise');

var ConfigLoader = require('../api/ConfigLoader');
var TestUtils = require('../api/TestUtils');

var configLoader = new ConfigLoader('app/config/config_test.yml');
var config = configLoader.load();

var utils = new TestUtils(config);

before(function() {

  this.timeout(10000);

  return new Promise(function(resolve, reject) {
    utils.cleanDb()
      .then(function() {
          Promise.all([
            utils.createUser('bill'),
            utils.createUser('sarah', ['ROLE_COURIER'])
          ]).then(resolve);
      })
      .catch(reject);
  });
});

describe('Connect to WebSocket without JWT', function() {
  it('should return 401 Unauthorized', function() {
    return new Promise(function (resolve, reject) {
      var ws = new WebSocket('http://localhost:8000');
      ws.onopen = reject;
      ws.onerror = function(e) {
        assert.equal('unexpected server response (401)', e.message);
        resolve();
      }
    });
  });
});

describe('Connect to WebSocket with JWT as customer', function() {
  it('should return 401 Unauthorized', function() {
    return new Promise(function (resolve, reject) {
      var token = utils.createJWT('bill');
      var ws = new WebSocket('http://localhost:8000', {
        headers: {
          Authorization: 'Bearer ' + token
        }
      });
      ws.onopen = reject;
      ws.onerror = function(e) {
        assert.equal('unexpected server response (401)', e.message);
        resolve();
      }
    });
  });
});

describe('Connect to WebSocket with JWT as courier', function() {
  it('should authorize connection', function() {
    return new Promise(function (resolve, reject) {
      var token = utils.createJWT('sarah');
      var ws = new WebSocket('http://localhost:8000', {
        headers: {
          Authorization: 'Bearer ' + token
        }
      });
      ws.onopen = resolve;
      ws.onerror = function(e) {
        reject(e.message);
      };
    });
  });
});
