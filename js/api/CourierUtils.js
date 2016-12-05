var Promise = require('promise');
var _ = require('underscore');

function CourierUtils(redis) {
  this.redis = redis;
}

CourierUtils.prototype.nearestCouriers = function(orderID) {
  var self = this;
  return new Promise(function(resolve, reject) {
    self.redis.georadiusbymember('GeoSet', 'order:' + orderID, 10, "km", 'WITHDIST', function(err, matches) {
      if (!matches) {
        return resolve([]);
      }
      matches = _.reject(matches, function(match) {
        return match[0].startsWith('order:');
      });
      resolve(matches);
    });
  });
}

module.exports = CourierUtils;