var Promise = require('promise');
var unserialize = require('locutus/php/var/unserialize');

function UserService(pgPool) {
  this.pgPool = pgPool;
}

UserService.prototype.findUserByUsername = function(username) {
  var self = this;
  return new Promise(function(resolve, reject) {
    self.pgPool.connect(function (err, client, done) {
      if (err) {
        return reject(err);
      }
      client.query('SELECT id, username, roles FROM api_user WHERE username = $1', [username], function (err, result) {
        done();

        if (result.rowCount === 0) {
          return resolve(null);
        }

        var user = result.rows[0];
        user.roles = unserialize(user.roles);
        resolve(user);
      });
    });
  });
}

module.exports = UserService;