var _ = require('lodash');

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
  this.declinedDeliveries.push(delivery)
};

Courier.prototype.hasDeclinedDelivery = function(delivery) {
  return _.includes(this.declinedDeliveries, delivery);
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

module.exports = Courier;
