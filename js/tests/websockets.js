var assert = require('assert');
var fs = require('fs');
var WebSocket = require('ws');
var ConfigLoader = require('../api/ConfigLoader');
var TestUtils = require('./utils');

var configLoader = new ConfigLoader('app/config/config_test.yml');
var config = configLoader.load();

var utils = new TestUtils(config);

var initUsers = function() {
  return new Promise(function(resolve, reject) {
    utils.waitServerUp('127.0.0.1', 8000)
         .then(function () {
            return utils.cleanDb();
          })
         .then(function() {
             Promise.all([
               utils.createUser('bill'),
               utils.createUser('sarah', ['ROLE_COURIER'])
             ]).then(resolve);
         })
         .catch(reject);
     });
};

describe('Connect to WebSocket', function() {

  before(function() {
    this.timeout(10000);

    return initUsers();
  });

  it('should return 401 Unauthorized without JWT', function() {
    return new Promise(function (resolve, reject) {
      var ws = new WebSocket('http://localhost:8000');
      ws.onopen = reject;
      ws.onerror = function(e) {
        assert.equal('unexpected server response (401)', e.message);
        resolve();
      };
    });
  });

  it('should return 401 Unauthorized with JWT as customer', function() {
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
      };
    });
  });

  it('should authorize connection with JWT as courier', function() {
    return new Promise(function (resolve, reject) {
      var token = utils.createJWT('sarah');
      var ws = new WebSocket('http://localhost:8000', {
        headers: {
          Authorization: 'Bearer ' + token
        }
      });
      ws.onopen = function() {
        ws.close();
        resolve();
      };
      ws.onerror = function(e) {
        reject(e.message);
      };
    });
  });
});
