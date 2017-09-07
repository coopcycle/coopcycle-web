var OrderRegistry = require('./OrderRegistry');
var Promise = require('promise');
var _ = require('underscore');

function Order() {
    this.state = Order.WAITING;
}

// Order states
Order.prototype.WAITING = Order.WAITING = 'WAITING';
Order.prototype.DISPATCHING = Order.DISPATCHING = 'DISPATCHING';
Order.prototype.ACCEPTED = Order.ACCEPTED = 'ACCEPTED';
Order.prototype.PICKED = Order.PICKED = 'PICKED';
Order.prototype.DELIVERED = Order.DELIVERED = 'DELIVERED';

Order.prototype.dispatch = function() {
  this.state = Order.DISPATCHING;
}

var REDIS;
var Db;

Order.init = function(redis, sequelize, db) {
  Order.Registry = new OrderRegistry(sequelize, redis);
  Db = db;
  REDIS = redis;
}

Order.load = function() {
  return new Promise(function(resolve, reject) {
    // Load all orders not delivered yet
    Db.Order.findAll({
      where: {
        status: {$in: [Order.WAITING, Order.ACCEPTED, Order.PICKED]},
      },
      include: [
        Db.Restaurant,
        { model: Db.User, as: 'courier' },
        { model: Db.User, as: 'customer' },
        {
          model: Db.Delivery,
          include: [
            { model: Db.Address, as: 'originAddress' },
            { model: Db.DeliveryAddress, as: 'deliveryAddress' }
          ]
        }
      ]
    }).then(function(orders) {

      var waiting = _.filter(orders, function(order) { return order.status === Order.WAITING });
      var delivering = _.filter(orders, function(order) { return order.status === Order.ACCEPTED || order.status === Order.PICKED });

      REDIS.del(['orders:waiting', 'orders:dispatching', 'orders:delivering'], function(err) {
        if (err) throw err;

        var deliveryAddresses = [];
        var restaurants = [];
        _.each(orders, function(order) {
          deliveryAddresses.push(order.delivery.deliveryAddress.position.longitude);
          deliveryAddresses.push(order.delivery.deliveryAddress.position.latitude);
          deliveryAddresses.push('order:' + order.id);

          restaurants.push(order.delivery.originAddress.position.longitude);
          restaurants.push(order.delivery.originAddress.position.latitude);
          restaurants.push('order:' + order.id);
        });
        if (deliveryAddresses.length > 0) {
          REDIS.geoadd('delivery_addresses:geo', deliveryAddresses);
        }
        if (restaurants.length > 0) {
          REDIS.geoadd('restaurants:geo', restaurants);
        }

        var ordersDelivering = [];
        _.each(delivering, function(order) {
          ordersDelivering.push('order:' + order.id);
          ordersDelivering.push('courier:' + order.courier.id);
        });
        if (ordersDelivering.length > 0) {
          REDIS.hmset('orders:delivering', ordersDelivering);
        }

        var waitingIds = waiting.map(function(order) {
          return order.id;
        });
        if (waitingIds.length > 0) {
          REDIS.rpush('orders:waiting', waitingIds);
        }

        // TODO Use Promise.all

        resolve();
      });
    });
  });
}

module.exports.Order = Order;
