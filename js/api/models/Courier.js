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

Courier.prototype.addDeclinedOrder = function(order) {
  this.declinedOrders.push(order);
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

  var restaurant = order.restaurant;

  return new Promise(function(resolve, reject) {
    REDIS.georadius('couriers:geo', restaurant.position.longitude, restaurant.position.latitude, distance, "m", 'WITHDIST', 'ASC', function(err, matches) {
      if (!matches) {
        return resolve(null);
      }

      // TODO async is useless
      async.filter(matches, function(match, cb) {
        var key = match[0];
        var courier = Courier.Pool.findByKey(key);
        if (!courier) {
          console.log('Courier ' + key + ' not found in pool');
          return cb(null, false);
        }
        cb(null, courier.isAvailable() && !courier.hasDeclinedOrder(order.id));
      }, function(err, results) {
        if (results.length === 0) {
          return resolve(null);
        }
        console.log('There are ' + results.length + ' couriers available');
        var first = results[0];
        var key = first[0];
        resolve(Courier.Pool.findByKey(key));
      });

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
