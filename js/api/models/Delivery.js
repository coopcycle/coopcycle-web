var Promise = require('promise');

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

module.exports = Delivery;
