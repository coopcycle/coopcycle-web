var assert = require('assert');
var fs = require('fs');
var WebSocket = require('ws');
var Promise = require('promise');
var jwt = require('jsonwebtoken');
var pg = require('pg');
var exec = require('child_process').exec;
var serialize = require('locutus/php/var/serialize');
var DatabaseCleaner = require('database-cleaner');

var ConfigLoader = require('../api/ConfigLoader');

var kernelRootDir = fs.realpathSync(__dirname + '/../../app');

var configLoader = new ConfigLoader('app/config/config_test.yml');
var config = configLoader.load();

var privateKeyPath = config.lexik_jwt_authentication.private_key_path.replace('%kernel.root_dir%', kernelRootDir);
var privateKey = fs.readFileSync(privateKeyPath);
var cert = {
  key: privateKey,
  passphrase: config.lexik_jwt_authentication.pass_phrase
}

var redis = require('redis').createClient({
  url: config.snc_redis.clients.default.dsn
});

var pgConfig = {
  user: config.doctrine.dbal.user,
  database: config.doctrine.dbal.dbname,
  password: config.doctrine.dbal.password,
  host: config.doctrine.dbal.host,
};

var pgCleaner = new DatabaseCleaner('postgresql');
var redisCleaner = new DatabaseCleaner('redis');

function createUser(username, roles) {
  return new Promise(function (resolve, reject) {

    var params = [
      username,
      username + '@coopcycle.dev',
      '123456'
    ]
    var command = 'php ' + kernelRootDir + '/../bin/console'
      + ' --env=test fos:user:create ' + params.join(' ');

    // Execute Symfony command to create user
    exec(command, function(error, stdout, stderr) {
      if (error) return reject();
      if (roles && Array.isArray(roles)) {
        pg.connect(pgConfig, function(err, client, release) {
          var sql = 'UPDATE api_user SET roles = $1 WHERE username = $2'
          client.query(sql, [serialize(roles), username], function (err, result) {
            if (err) throw err;
            client.end();
            resolve();
          });
        });
      } else {
        resolve();
      }
    });
  });
}

before(function() {

  this.timeout(10000);

  return new Promise(function(resolve, reject) {
    pg.connect(pgConfig, function(err, client, release) {
      if (err) return reject();

      var cleanRedis = new Promise(function(resolve, reject) {
        redisCleaner.clean(redis, resolve);
      });
      var cleanPg = new Promise(function(resolve, reject) {
        pgCleaner.clean(client, resolve);
      });

      Promise.all([
        cleanRedis,
        cleanPg,
        createUser('bill'),
        createUser('sarah', ['ROLE_COURIER'])
      ]).then(resolve);
    });
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
      var token = jwt.sign({ username: 'bill' }, cert, { algorithm: 'RS256' });
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
      var token = jwt.sign({ username: 'sarah' }, cert, { algorithm: 'RS256' });
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
