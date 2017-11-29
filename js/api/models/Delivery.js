var DeliveryRegistry = require('./DeliveryRegistry');
var Promise = require('promise');
var _ = require('underscore');

var winston = require('winston');
winston.level = process.env.NODE_ENV === 'production' ? 'info' : 'debug';

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
};

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

      winston.info('Found ' + orders.length + ' orders to manage')

      const deliveries = _.map(orders, order => order.delivery);

      const waiting = _.filter(deliveries, function(delivery) {
        return delivery.status === Delivery.WAITING
      });
      const delivering = _.filter(deliveries, function(delivery) {
        return delivery.status === Delivery.DISPATCHED || delivery.status === Delivery.PICKED;
      });

      const keysToDel = [
        'deliveries:waiting',
        'deliveries:dispatching',
        'deliveries:delivering',
        'delivery_addresses:geo',
        'restaurants:geo'
      ];

      REDIS.del(keysToDel, function(err) {
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

        var deliveringArgs = [];

        // Compile Redis commands
        _.each(delivering, function(delivery) {
          deliveringArgs.push('delivery:' + delivery.id);
          deliveringArgs.push('courier:' + delivery.courier.id);
        });
        // Execute Redis commands at once
        if (deliveringArgs.length > 0) {
          REDIS.hmset('deliveries:delivering', deliveringArgs);
        }

        var waitingArgs = waiting.map(function(delivery) {
          return delivery.id;
        });
        if (waitingArgs.length > 0) {
          REDIS.rpush('deliveries:waiting', waitingArgs);
        }

        // TODO Use Promise.all

        resolve();
      });
    });
  });
};

module.exports = Delivery;
