var jwt = require('jsonwebtoken');
var _ = require('lodash');
var fs = require('fs');

const AUTHENTICATION_NOT_FOUND = 'AUTHENTICATION_NOT_FOUND';
const USER_NOT_FOUND = 'USER_NOT_FOUND';
const TOKEN_NOT_VALID = 'TOKEN_NOT_VALID'

let certCache

function getCert(path) {

  return new Promise((resolve, reject) => {

    if (certCache) {
      resolve(certCache)
    } else {
      fs.readFile(path, function (err, data) {
        if (err) {
          return reject(err)
        }
        certCache = data
        resolve(data)
      })
    }

  })
}

function TokenVerifier(certPath, db) {
  this.certPath = certPath;
  this.db = db;
}

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

    getCert(this.certPath).then(cert => {
      // var self = this;
      jwt.verify(token, cert, (err, decoded) => {
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
        } else if (decoded.hasOwnProperty('ord')) {
          resolve({ order: decoded.ord })
        }

      });
    })


  });

};

module.exports = TokenVerifier;
