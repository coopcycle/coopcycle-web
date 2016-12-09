var REDIS;

function OrderDispatcher(redis, orderRegistry) {
  REDIS = redis;
  this.orderRegistry = orderRegistry;
  this.timeout = null;
  this.handler = null;
}

function next(orderRegistry, handler) {
  timeout = setTimeout(circularListHandler.bind(null, orderRegistry, handler), 1000);
}

function circularListHandler(orderRegistry, handler) {
  REDIS.rpoplpush('orders:waiting', 'orders:waiting', function(err, orderID) {
    if (!orderID) {
      console.log('No orders to process yet');
      return next(orderRegistry, handler);
    }
    orderRegistry.findById(orderID).then(function(order) {
      handler.call(null, order, next.bind(null, orderRegistry, handler));
    });
  });
}

OrderDispatcher.prototype.setHandler = function(handler) {
  this.handler = handler;
}

OrderDispatcher.prototype.start = function() {
  this.stop();
  circularListHandler(this.orderRegistry, this.handler);
}

OrderDispatcher.prototype.stop = function() {
  clearTimeout(this.timeout);
}

module.exports = OrderDispatcher;