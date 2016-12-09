var OrderRegistry = require('./OrderRegistry');

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

module.exports.Order = Order;