var _ = require('lodash');
var Utils = require('../Utils');

function CourierPool(redis) {
  this.pool = [];
  this.redis = redis;
}

CourierPool.prototype.add = function(courier) {
  this.pool.push(courier);
};

CourierPool.prototype.remove = function(courier) {

  console.log('Removing courier "' + courier.id + '" from Redis...');

  this.redis.zrem('couriers:geo', 'courier:' + courier.id);

  var index = this.pool.indexOf(courier);
  if (-1 !== index) {
    this.pool.splice(index, 1);
  }

  if (courier.state === 'DISPATCHING' && courier.delivery) {
    console.log('Releasing delivery #' + courier.delivery);
    var redis = this.redis;
    redis.lrem('deliveries:dispatching', 0, courier.delivery, function(err) {
      if (err) throw err;
      redis.lpush('deliveries:waiting', courier.delivery, function(err) {
        if (err) throw err;
      });
    });
  }
};

CourierPool.prototype.removeAll = function() {
  console.log('Removing all couriers from Redis...');
  // TODO simply DEL the set ?
  var keys = _.map(this.pool, function(courier) {
    return 'courier:' + courier.id;
  });
  if (keys.length > 0) {
    this.redis.zrem('couriers:geo', keys);
  }
};

CourierPool.prototype.size = function(courier) {
  return _.size(this.pool);
};

CourierPool.prototype.findById = function(id) {
  return _.find(this.pool, function(courier) {
    return parseInt(courier.id, 10) === parseInt(id, 10);
  });
};

CourierPool.prototype.findByKey = function(key) {
  return this.findById(Utils.keyAsInt(key));
};

CourierPool.prototype.forEach = function(cb) {
  this.pool.forEach(cb);
};

module.exports = CourierPool;
