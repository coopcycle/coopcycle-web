var jwt = require('jsonwebtoken');
var _ = require('lodash');

function TokenVerifier(cert, db) {
  this.cert = cert;
  this.db = db;
}

const AUTHENTICATION_NOT_FOUND = 'AUTHENTICATION_NOT_FOUND';
const USER_NOT_FOUND = 'USER_NOT_FOUND';
const TOKEN_NOT_VALID = 'TOKEN_NOT_VALID'

TokenVerifier.prototype.verify = function(token) {

  return new Promise((resolve, reject) => {

    const isString = typeof token === 'string' || token instanceof String;

    if (!isString) {
      token = token.authorization;
      if (!token) {
        console.log('No JWT found in request');

        return reject(AUTHENTICATION_NOT_FOUND)
      }
    }

    token = token.replace('Bearer ', '');

    // var self = this;
    jwt.verify(token, this.cert, (err, decoded) => {
      if (err) {
        console.log('Invalid JWT', err.toString());

        return reject(TOKEN_NOT_VALID)
      }

      console.log('JWT verified successfully', decoded);

      // The claim "username" is added by LexikJWTAuthenticationBundle
      if (decoded.hasOwnProperty('username')) {
        this.db.User
          .findOne({
            where: { username: decoded.username }
          })
          .then(function(user) {

            if (!user) {
              console.log('User does not exist');

              return reject(USER_NOT_FOUND)
            }

            resolve(user)
          })
          .catch(function (e) {
            reject(e)
          })
      } else if (decoded.hasOwnProperty('msn')) {
        resolve({ courier: decoded.msn })
      }

    });
  });

};

module.exports = TokenVerifier;
