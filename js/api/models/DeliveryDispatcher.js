var REDIS;
var TIMEOUT;

var winston = require('winston');
winston.level = process.env.NODE_ENV === 'production' ? 'info' : 'debug';

function Dispatcher(redis, registry) {
  REDIS = redis;
  this.registry = registry;
  this.handler = null;
}

function next(registry, handler) {
  TIMEOUT = setTimeout(circularListHandler.bind(null, registry, handler), 1000);
}

function circularListHandler(registry, handler) {
  REDIS.rpoplpush('deliveries:waiting', 'deliveries:waiting', function(err, deliveryID) {
    if (err) throw err;
    if (!deliveryID) {
      // winston.debug('No deliveries to process yet');
      return next(registry, handler);
    }

    // winston.debug('Dispatching delivery #' + deliveryID);
    registry.findById(deliveryID)
      .then(function(delivery) {
        handler.call(null, delivery, next.bind(null, registry, handler));
      })
      .catch(function(err) {
        winston.error(err);
        next(registry, handler);
      });
  });
}

Dispatcher.prototype.setHandler = function(handler) {
  this.handler = handler;
};

Dispatcher.prototype.start = function() {
  this.stop();
  circularListHandler(this.registry, this.handler);
};

Dispatcher.prototype.stop = function() {
  clearTimeout(TIMEOUT);
};

module.exports = Dispatcher;
