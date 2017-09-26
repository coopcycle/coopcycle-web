var DeliveryRegistry = require('./DeliveryRegistry');
var Promise = require('promise');
var _ = require('underscore');

function Delivery() {
  this.state = Delivery.WAITING;
}

// Delivery states
Delivery.prototype.WAITING = Delivery.WAITING = 'WAITING';
Delivery.prototype.DISPATCHED = Delivery.DISPATCHED = 'DISPATCHED';
Delivery.prototype.PICKED = Delivery.PICKED = 'PICKED';
Delivery.prototype.DELIVERED = Delivery.DELIVERED = 'DELIVERED';

var REDIS;
var Db;

Delivery.init = function(redis, sequelize, db) {
  Delivery.Registry = new DeliveryRegistry(sequelize, redis);
  Db = db;
  REDIS = redis;
}

Delivery.load = function() {
  return new Promise(function(resolve, reject) {
    // Load all deliveries which have not been delivered yet
    Db.Order.findAll({
      include: [
        Db.Restaurant,
        { model: Db.User, as: 'customer' },
        {
          model: Db.Delivery,
          where: {
            status: {$in: [Delivery.WAITING, Delivery.DISPATCHED, Delivery.PICKED]},
          },
          include: [
            { model: Db.User, as: 'courier' },
            { model: Db.Address, as: 'originAddress' },
            { model: Db.Address, as: 'deliveryAddress' }
          ]
        }
      ]
    }).then(function(orders) {

      const deliveries = _.map(orders, order => order.delivery);

      const waiting = _.filter(deliveries, function(delivery) {
        return delivery.status === Delivery.WAITING
      });
      const delivering = _.filter(deliveries, function(delivery) {
        return delivery.status === Delivery.DISPATCHED || delivery.status === Delivery.PICKED;
      });

      REDIS.del(['deliveries:waiting', 'deliveries:dispatching', 'deliveries:delivering'], function(err) {
        if (err) throw err;

        var deliveryAddresses = [];
        var restaurants = [];

        // Compile Redis commands
        _.each(deliveries, function(delivery) {
          deliveryAddresses.push(delivery.deliveryAddress.position.longitude);
          deliveryAddresses.push(delivery.deliveryAddress.position.latitude);
          deliveryAddresses.push('delivery:' + delivery.id);

          restaurants.push(delivery.originAddress.position.longitude);
          restaurants.push(delivery.originAddress.position.latitude);
          restaurants.push('delivery:' + delivery.id);
        });
        // Execute Redis commands at once
        if (deliveryAddresses.length > 0) {
          REDIS.geoadd('delivery_addresses:geo', deliveryAddresses);
        }
        if (restaurants.length > 0) {
          REDIS.geoadd('restaurants:geo', restaurants);
        }

        var deliveries = [];

        // Compile Redis commands
        _.each(delivering, function(delivery) {
          deliveries.push('delivery:' + delivery.id);
          deliveries.push('courier:' + delivery.courier.id);
        });
        // Execute Redis commands at once
        if (deliveries.length > 0) {
          REDIS.hmset('deliveries:delivering', deliveries);
        }

        var waitingIds = waiting.map(function(delivery) {
          return delivery.id;
        });
        if (waitingIds.length > 0) {
          REDIS.rpush('deliveries:waiting', waitingIds);
        }

        // TODO Use Promise.all

        resolve();
      });
    });
  });
}

module.exports = Delivery;
