var Promise = require('promise');

var Db;

function OrderRegistry(sequelize, redis) {
  this.cache = {};
  this.redis = redis;
  Db = require('../Db')(sequelize);
}

OrderRegistry.prototype.findById = function(id) {
  var self = this;
  return new Promise(function(resolve, reject) {
    var order = self.cache[id];
    if (!order) {
      return Db.Order.findById(id, {include: [Db.Restaurant, Db.DeliveryAddress]})
        .then(function(order) {
          if (!order) {
            return reject('Could not load order #' + id + ' from database, skipping...');
          }

          var restaurant = order.restaurant;
          self.redis.geoadd('orders:geo', restaurant.position.longitude, restaurant.position.latitude, 'order:' + id, function(err) {
            if (err) throw err;
            self.cache[id] = order;
            resolve(order);
          });

        });
    }
    resolve(order);
  });
}

module.exports = OrderRegistry;