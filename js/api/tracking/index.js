var path = require('path');
var _ = require('lodash');
var http = require('http');
var winston = require('winston')

const TokenVerifier = require('../TokenVerifier')

const logger = winston.createLogger({
  level: process.env.NODE_ENV === 'production' ? 'info' : 'debug',
  format: winston.format.json(),
  transports: [
    new winston.transports.Console({
      format: winston.format.simple()
    })
  ]
})

var ROOT_DIR = __dirname + '/../../..';

console.log('---------------------');
console.log('- STARTING TRACKING -');
console.log('---------------------');

console.log('NODE_ENV = ' + process.env.NODE_ENV);
console.log('NODE_APP_INSTANCE = ' + process.env.NODE_APP_INSTANCE)
console.log('PORT = ' + process.env.PORT);

const {
  sub,
  sequelize
} = require('./config')(ROOT_DIR)

const db = require('../Db')(sequelize)

const server = http.createServer(function(request, response) {
    // process HTTP request. Since we're writing just WebSockets server
    // we don't have to implement anything.
});

const tokenVerifier = new TokenVerifier(ROOT_DIR + '/var/jwt/public.pem', db)

const io = require('socket.io')(server, { path: '/tracking/socket.io' });

sub.on('psubscribe', (channel, count) => {
  logger.info(`Subscribed to ${channel} (${count})`)
  if (count === 2) {
    initialize()
  }
})

sub.prefixedPSubscribe('users:*')
sub.prefixedPSubscribe('couriers:*')

const authMiddleware = function(socket, next) {

  // @see https://stackoverflow.com/questions/36788831/authenticating-socket-io-connections

  if (socket.handshake.query && socket.handshake.query.token) {

    tokenVerifier.verify(socket.handshake.query.token)
      .then(user => {
        if (user instanceof db.User) {
          socket.user = user;
        } else {
          if (user.hasOwnProperty('courier')) {
            socket.courier = user.courier
          }
        }
        next();
      })
      .catch(e => next(new Error('Authentication error')))

  } else {
    next(new Error('Authentication error'));
  }
}

function initialize() {

  sub.on('pmessage', function(patternWithPrefix, channelWithPrefix, message) {

    logger.debug(`Received pmessage on channel ${channelWithPrefix}`)

    const channel = sub.unprefixedChannel(channelWithPrefix)
    const pattern = sub.unprefixedChannel(patternWithPrefix)

    message = JSON.parse(message)

    logger.debug(`Emitting "${message.name}" to sockets in ${channel}`)

    io.in(channel).emit(message.name, message.data)

  })

  io
    .use(authMiddleware)
    .on('connect', function (socket) {
      if (socket.user) {
        socket.join(`users:${socket.user.username}`, (err) => {
          if (!err) {
            console.log(`user "${socket.user.username}" joined room "users:${socket.user.username}"`)
          }
        })
      } else {
        socket.join(`couriers:${socket.courier}`, (err) => {
          if (!err) {
            console.log(`user joined room "couriers:${socket.courier}"`)
          }
        })
      }
    })

  server.listen(process.env.PORT || 8001);

}
