var WebSocket = require('ws')
var WebSocketServer = WebSocket.Server
var http = require('http')
var _ = require('lodash')
var winston = require('winston')

var TokenVerifier = require('../TokenVerifier')

const logger = winston.createLogger({
  level: process.env.NODE_ENV === 'production' ? 'info' : 'debug',
  format: process.env.NODE_ENV === 'production' ? winston.format.json() : winston.format.simple(),
  transports: [
    new winston.transports.Console({
      format: winston.format.simple()
    })
  ]
})

console.log('------------------------')
console.log('- STARTING DISPATCH V2 -')
console.log('------------------------')

console.log('NODE_ENV = ' + process.env.NODE_ENV)
console.log('PORT = ' + process.env.PORT)

const {
  pub,
  sub,
  sequelize,
  redis
} = require('./config')()

const db = require('../Db')(sequelize)

var server = http.createServer(function(request, response) {
    // process HTTP request. Since we're writing just WebSockets server
    // we don't have to implement anything.
});

var tokenVerifier = new TokenVerifier(process.env.COOPCYCLE_PUBLIC_KEY_FILE, db)

var wsServer = new WebSocketServer({
    server: server,
    verifyClient: function (info, cb) {
      tokenVerifier
        .verify(info.req.headers)
        .then((user) => {
          info.req.user = user;
          cb(true);
        })
        .catch(e => {
          cb(false, 401, 'Access denied');
        });
    },
})

let rooms = {}

sub.on('psubscribe', (channel, count) => {
  logger.info(`Subscribed to ${channel} (${count})`)
  initialize()
})

// @see https://github.com/websockets/ws#how-to-detect-and-close-broken-connections

function noop() {}

function heartbeat() {
  this.isAlive = true
}

function initialize() {

  sub.on('pmessage', function(patternWithPrefix, channelWithPrefix, message) {

    logger.debug(`Received pmessage on channel ${channelWithPrefix}`)

    const channel = sub.unprefixedChannel(channelWithPrefix)
    const pattern = sub.unprefixedChannel(patternWithPrefix)
    const decoded = JSON.parse(message)

    if (rooms[channel]) {
      logger.debug(`Emitting "${decoded.name}" to sockets in ${channel}`)
      _.forEach(rooms[channel], ws => {
        if (ws.readyState === WebSocket.OPEN) {
          ws.send(message)
        }
      })
    }

  })

  // WebSocket server
  wsServer.on('connection', function(ws, req) {

    ws.isAlive = true
    ws.on('pong', heartbeat)

    const { user } = req

    logger.info(`User ${user.username} connected`)

    if (!rooms[`users:${user.username}`]) {
      rooms[`users:${user.username}`] = []
    }

    rooms[`users:${user.username}`].push(ws)

    ws.on('close', function() {
      logger.info(`User ${user.username} disconnected`)
      if (rooms[`users:${user.username}`]) {
        rooms[`users:${user.username}`] = _.filter(rooms[`users:${user.username}`], socket => socket !== ws)
      }
    })

  })

  const interval = setInterval(function ping() {
    wsServer.clients.forEach(function each(ws) {
      if (ws.isAlive === false) return ws.terminate();

      ws.isAlive = false;
      ws.ping(noop)
    })
  }, 30000)

  wsServer.on('close', function close() {
    clearInterval(interval);
  });

  server.listen(process.env.PORT || 8000, function() {})
}

sub.prefixedPSubscribe('users:*')

// Handle restarts
process.on('SIGINT', function () {
  console.log('------------------------')
  console.log('- STOPPING DISPATCH V2 -')
  console.log('------------------------')
})
