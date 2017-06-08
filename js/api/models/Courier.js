var Promise = require('promise');
var _ = require('underscore');
var async = require('async');
var RedisRepository = require('../RedisRepository');
var CourierPool = require('./CourierPool');
var Utils = require('../Utils');

function Courier(data) {
  this.state = data.state || Courier.UNKNOWN;
  this.id = data.id;
  this.username = data.username;
  this.ws = undefined;
  this.order = undefined;
  this.declinedOrders = [];
}

// Courier states
Courier.prototype.UNKNOWN = Courier.UNKNOWN = 'UNKNOWN';
Courier.prototype.AVAILABLE = Courier.AVAILABLE = 'AVAILABLE';
Courier.prototype.DISPATCHING = Courier.DISPATCHING = 'DISPATCHING';
Courier.prototype.DELIVERING = Courier.DELIVERING = 'DELIVERING';

Courier.prototype.connect = function(ws) {
  this.ws = ws;
}

Courier.prototype.setState = function(state) {
  this.state = state;
}

Courier.prototype.setOrder = function(order) {
  this.order = order;
}

Courier.prototype.declineOrder = function(order) {
  if (this.order !== order) {
    console.log('Order #' + order + ' was not dispatched to courier #' + this.id);
    return;
  }
  REDIS.lrem('orders:dispatching', 0, order, (err) => {
    if (err) throw err;
    REDIS.lpush('orders:waiting', order, (err) => {
      if (err) throw err;
      this.order = null;
      this.state = Courier.UNKNOWN;
      this.declinedOrders.push(order);
    });
  });
}

Courier.prototype.hasDeclinedOrder = function(order) {
  return _.contains(this.declinedOrders, order);
}

Courier.prototype.isAvailable = function() {
  return this.state === Courier.AVAILABLE;
}

Courier.prototype.save = function(cb) {
  return Courier.Repository.save(this);
}

Courier.prototype.send = function(message) {
  this.ws.send(JSON.stringify(message));
}

Courier.prototype.toJSON = function() {
  return {
    id: this.id,
    username: this.username,
    state: this.state,
    order: this.order,
    declinedOrders: this.declinedOrders,
  }
}

/** Static methods **/

Courier.nearestForOrder = function(order, distance) {

  var address = order.restaurant.address;

  return new Promise(function(resolve, reject) {

    REDIS.georadius('couriers:geo', address.position.longitude, address.position.latitude, distance, "m", 'WITHDIST', 'ASC', function(err, matches) {
      if (!matches) {
        return resolve(null);
      }

      // Keep only available couriers
      var results = _.filter(matches, (match) => {
        var key = match[0];
        var courier = Courier.Pool.findByKey(key);
        if (!courier) {
          console.log('Courier ' + key + ' not found in pool');
          return false;
        }

        return courier.isAvailable() && !courier.hasDeclinedOrder(order.id);
      });

      if (results.length === 0) {
        return resolve(null);
      }

      console.log('There are ' + results.length + ' couriers available');

      // Return nearest courier
      var first = results[0];
      var key = first[0];

      return resolve(Courier.Pool.findByKey(key));
    });
  });
}

Courier.updateCoordinates = function(courier, coordinates) {
  if (courier.state === Courier.UNKNOWN) {
    console.log('Position received!')
    courier.setState(Courier.AVAILABLE);
  }
  REDIS.geoadd('couriers:geo',
    coordinates.longitude,
    coordinates.latitude,
    Utils.resolveKey('courier', courier.id)
  );
}

var REDIS;

Courier.init = function(redis, redisPubSub) {
  Courier.Pool = new CourierPool(redis, redisPubSub);
  Courier.Repository = new RedisRepository(redis, 'courier');
  REDIS = redis;
}

module.exports.Courier = Courier;
