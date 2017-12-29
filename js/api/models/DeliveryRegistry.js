var Promise = require('promise');

var Db;

function DeliveryRegistry(sequelize, redis) {
  this.cache = {};
  this.redis = redis;
  Db = require('../Db')(sequelize);
}

DeliveryRegistry.prototype.findById = function(id) {
  var self = this;
  const options = {
    include: [
      { model: Db.Address, as: 'originAddress' },
      { model: Db.Address, as: 'deliveryAddress' },
      { model: Db.Order, as: 'order' }
    ]
  };
  return new Promise(function(resolve, reject) {
    var cached = self.cache[id];
    if (!cached) {
      return Db.Delivery.findById(id, options)
        .then(function(delivery) {
          if (!delivery) {
            return reject('Could not load delivery #' + id + ' from database, skipping...');
          }
          const longitude = delivery.deliveryAddress.position.longitude;
          const latitude = delivery.deliveryAddress.position.longitude;

          self.redis.geoadd('deliveries:geo', longitude, latitude, 'delivery:' + id, function(err) {
            if (err) throw err;
            self.cache[id] = delivery;
            resolve(delivery);
          });

        });
    }
    resolve(cached);
  });
};

module.exports = DeliveryRegistry;
