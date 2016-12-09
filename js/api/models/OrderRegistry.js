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

          var restaurant = {
            latitude: order.restaurant.geo.coordinates[0],
            longitude: order.restaurant.geo.coordinates[1],
          }
          self.redis.geoadd('orders:geo', restaurant.longitude, restaurant.latitude, 'order:' + id, function(err) {
            self.cache[id] = order;
            resolve(order);
          });

          // var deliveryAddress = {
          //   latitude: order.delivery_address.geo.coordinates[0],
          //   longitude: order.delivery_address.geo.coordinates[1],
          // }
          // self.redis.geoadd('delivery_address:geo', deliveryAddress.longitude, deliveryAddress.latitude, 'order:' + id);
        });
    }
    resolve(order);
  });
}

module.exports = OrderRegistry;