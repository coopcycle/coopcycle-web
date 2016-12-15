var Courier = require('./models/Courier').Courier;
var jwt = require('jsonwebtoken');
var _ = require('underscore');

function TokenVerifier(cert, db) {
  this.cert = cert;
  this.db = db;
}

TokenVerifier.prototype.verify = function (info, cb) {

  var token = info.req.headers.authorization;
  if (!token) {
    console.log('No JWT found in request');
    return cb(false, 401, 'Unauthorized');
  }

  token = token.substring('Bearer '.length);

  var self = this;
  jwt.verify(token, this.cert, function (err, decoded) {
    if (err) {
      console.log('Invalid JWT', err.toString());
      cb(false, 401, 'Access denied');
    } else {
      console.log('JWT verified successfully', decoded);
      // Token is verified, load user from database
      self.db.Courier.findOne({where: {username: decoded.username}})
        .then(function(user) {

          if (!user) {
            console.log('User does not exist')
            return cb(false, 401, 'Access denied');
          }
          if (!_.contains(user.roles, 'ROLE_COURIER')) {
            console.log('User has not enough access rights')
            return cb(false, 401, 'Access denied');
          }

          info.req.user = user;

          var state = Courier.UNKNOWN;

          // Check courier status
          self.db.Order.findOne({
            where: {
              status: {$in: ['ACCEPTED', 'PICKED']},
              courier_id: user.id
            }
          }).then(function(order) {

            if (order) {
              state = Courier.DELIVERING;
              console.log('Courier #' + user.id + ' was delivering order #' + order.id);
            } else {
              console.log('Courier #' + user.id + ' was not delivering an order');
            }

            console.log('Courier #' + user.id + ', setting state = ' + state);

            var courier = new Courier(_.extend(user.toJSON(), {
              state: state
            }));

            info.req.courier = courier;
            cb(true);

          });
        });
    }
  });
}

module.exports = TokenVerifier;