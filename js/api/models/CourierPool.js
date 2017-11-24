var _ = require('underscore');
var Utils = require('../Utils');

function CourierPool(redis, redisPubSub) {
  this.pool = [];
  this.redis = redis;

  redisPubSub.prefixedSubscribe('couriers');
  redisPubSub.prefixedSubscribe('couriers:available');
  redisPubSub.prefixedSubscribe('deliveries:declined');

  var self = this;
  redisPubSub.on('message', function(channel, message) {
    if (channel === 'couriers') {
      console.log('Courier #' + message + ' has accepted delivery');
      var courier = self.findById(message);
      if (courier) {
        courier.setState('DELIVERING');
      }
    }
    if (channel === 'couriers:available') {
      console.log('Courier #' + message + ' is available again');
      var courier = self.findById(message);
      if (courier) {
        courier.setState('AVAILABLE');
      }
    }
    if (channel === 'deliveries:declined') {
      var data = JSON.parse(message);
      console.log('Courier #' + data.courier + ' has declined delivery #' + data.delivery);
      var courier = self.findById(data.courier);
      courier.declineDelivery(data.delivery);
    }
  });
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

module.exports = CourierPool;
