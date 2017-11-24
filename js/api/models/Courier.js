var Promise = require('promise');
var _ = require('underscore');
var RedisRepository = require('../RedisRepository');
var CourierPool = require('./CourierPool');
var Utils = require('../Utils');

function Courier(data) {
  this.state = data.state || Courier.UNKNOWN;
  this.id = data.id;
  this.username = data.username;
  this.ws = undefined;
  this.delivery = undefined;
  this.declinedDeliveries = [];
}

// Courier states
Courier.prototype.UNKNOWN = Courier.UNKNOWN = 'UNKNOWN';
Courier.prototype.AVAILABLE = Courier.AVAILABLE = 'AVAILABLE';
Courier.prototype.DISPATCHING = Courier.DISPATCHING = 'DISPATCHING';
Courier.prototype.DELIVERING = Courier.DELIVERING = 'DELIVERING';

Courier.prototype.connect = function(ws) {
  this.ws = ws;
};

Courier.prototype.setState = function(state) {
  this.state = state;
};

Courier.prototype.setDelivery = function(delivery) {
  this.delivery = delivery;
};

Courier.prototype.declineDelivery = function(delivery) {
  if (this.delivery !== delivery) {
    console.log('Delivery #' + delivery + ' was not dispatched to courier #' + this.id);
    return;
  }
  REDIS.lrem('deliveries:dispatching', 0, delivery, (err) => {
    if (err) throw err;
    REDIS.lpush('deliveries:waiting', delivery, (err) => {
      if (err) throw err;
      this.delivery = null;
      this.state = Courier.UNKNOWN;
      this.declinedDeliveries.push(delivery);
    });
  });
};

Courier.prototype.hasDeclinedDelivery = function(delivery) {
  return _.contains(this.declinedDeliveries, delivery);
};

Courier.prototype.isAvailable = function() {
  return this.state === Courier.AVAILABLE;
};

Courier.prototype.save = function(cb) {
  return Courier.Repository.save(this);
};

Courier.prototype.send = function(message) {
  this.ws.send(JSON.stringify(message));
};

Courier.prototype.toJSON = function() {
  return {
    id: this.id,
    username: this.username,
    state: this.state,
    delivery: this.delivery,
    declinedDeliveries: this.declinedDeliveries,
  }
};

/** Static methods **/
/*
  Find the closest available courier for a given delivery.

  @param Delivery delivery The handled delivery
  @param int distance The radius from the customer position in which we are looking for a courier
 */

Courier.nearestForDelivery = function(delivery, distance = 3500) {

  var address = delivery.deliveryAddress;

  return new Promise(function(resolve, reject) {

    // Returns all the couriers which are in distance from the delivery address
    REDIS.georadius('couriers:geo', address.position.longitude, address.position.latitude, distance, "m", 'WITHDIST', 'ASC', function(err, matches) {
      if (!matches) {
        return resolve(null);
      }

      // Filter couriers :
      //  - courier that are available
      //  - courier that didn't already refuse the delivery
      var results = _.filter(matches, (match) => {
        var key = match[0];
        var courier = Courier.Pool.findByKey(key);
        if (!courier) {
          console.log('Courier ' + key + ' not found in pool');
          return false;
        }

        return courier.isAvailable() && !courier.hasDeclinedDelivery(delivery.id);
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
};

Courier.updateCoordinates = function(courier, coordinates) {
  if (courier.state === Courier.UNKNOWN) {
    console.log('Position received!');
    courier.setState(Courier.AVAILABLE);
  }
  REDIS.geoadd('couriers:geo',
    coordinates.longitude,
    coordinates.latitude,
    Utils.resolveKey('courier', courier.id)
  );
};

var REDIS;

Courier.init = function(redis, redisPubSub) {
  Courier.Pool = new CourierPool(redis, redisPubSub);
  Courier.Repository = new RedisRepository(redis, 'courier');
  REDIS = redis;
};

module.exports.Courier = Courier;
