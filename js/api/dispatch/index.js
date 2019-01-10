var WebSocketServer = require('ws').Server
var http = require('http')
var fs = require('fs')
var _ = require('lodash')
var moment = require('moment')

var winston = require('winston')
winston.level = process.env.NODE_ENV === 'production' ? 'info' : 'debug'

var ROOT_DIR = __dirname + '/../../..'

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
} = require('./config')(ROOT_DIR)

const db = require('../Db')(sequelize)

var server = http.createServer(function(request, response) {
    // process HTTP request. Since we're writing just WebSockets server
    // we don't have to implement anything.
});

var cert = fs.readFileSync(ROOT_DIR + '/var/jwt/public.pem')
var TokenVerifier = require('../TokenVerifier')
var tokenVerifier = new TokenVerifier(cert, db)

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

let isClosing = false
let rooms = {}

sub.on('psubscribe', (channel, count) => {
  winston.info(`Subscribed to ${channel} (${count})`)
  initialize()
})

function initialize() {

  sub.on('pmessage', function(patternWithPrefix, channelWithPrefix, message) {

    winston.debug(`Received pmessage on channel ${channelWithPrefix}`)

    const channel = sub.unprefixedChannel(channelWithPrefix)
    const pattern = sub.unprefixedChannel(patternWithPrefix)
    const decoded = JSON.parse(message)

    if (rooms[channel]) {
      winston.debug(`Emitting "${decoded.name}" to sockets in ${channel}`)
      _.forEach(rooms[channel], ws => ws.send(message))
    }

  })

  // WebSocket server
  wsServer.on('connection', function(ws, req) {

    const { user } = req

    console.log(`User ${user.username} connected`)

    if (!rooms[`users:${user.username}`]) {
      rooms[`users:${user.username}`] = []
    }

    rooms[`users:${user.username}`].push(ws)

    ws.on('close', function() {
      console.log(`User ${user.username} disconnected`)
      if (rooms[`users:${user.username}`]) {
        rooms[`users:${user.username}`] = _.filter(rooms[`users:${user.username}`], socket => socket !== ws)
      }
    })

  })

  server.listen(process.env.PORT || 8000, function() {})
}

sub.prefixedPSubscribe('users:*')

// Handle restarts
process.on('SIGINT', function () {

  console.log('------------------------')
  console.log('- STOPPING DISPATCH V2 -')
  console.log('------------------------')

  isClosing = true;

})
