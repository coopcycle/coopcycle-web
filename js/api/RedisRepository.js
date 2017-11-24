var Utils = require('./Utils');
var _ = require('underscore');

function RedisRepository(redis, prefix) {
  this.redis = redis;
  this.prefix = prefix;
}

RedisRepository.prototype.load = function(key) {
  var self = this;
  return new Promise(function(resolve, reject) {
    self.redis.get(Utils.resolveKey(self.prefix, key), function(err, result) {
      if (err) return reject(err);
      resolve(result ? JSON.parse(result) : null);
    });
  });
};

RedisRepository.prototype.save = function(model) {
  var self = this;

  var keysValues = [];
  _.each(model.toJSON(), function(value, key) {
    if (key !== 'id') {
      keysValues.push(key);
      keysValues.push(JSON.stringify(value));
    }
  });

  return new Promise(function(resolve, reject) {
    var key = Utils.resolveKey(self.prefix, model.id);
    self.redis.set(key, JSON.stringify(model.toJSON()), function(err) {
      if (err) return reject(err);
      resolve(model);
    })
  });
};

module.exports = RedisRepository;