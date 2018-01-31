var WebSocketServer = require('ws').Server
var http = require('http')
var fs = require('fs')
var co = require('co')
var _ = require('lodash')

var winston = require('winston')
winston.level = process.env.NODE_ENV === 'production' ? 'info' : 'debug'

var ROOT_DIR = __dirname + '/../../..'

console.log('------------------------')
console.log('- STARTING DISPATCH V2 -')
console.log('------------------------')

console.log('NODE_ENV = ' + process.env.NODE_ENV)
console.log('PORT = ' + process.env.PORT)

const {
  metrics,
  redis,
  pub,
  sub,
  sequelize
} = require('./config')(ROOT_DIR)

const db = require('../Db')(sequelize)

var server = http.createServer(function(request, response) {
    // process HTTP request. Since we're writing just WebSockets server
    // we don't have to implement anything.
});
server.listen(process.env.PORT || 8000, function() {})

var cert = fs.readFileSync(ROOT_DIR + '/var/jwt/public.pem')
var TokenVerifier = require('../TokenVerifier')
var tokenVerifier = new TokenVerifier(cert, db)

var wsServer = new WebSocketServer({
    server: server,
    verifyClient: function (info, cb) {
      tokenVerifier.verify(info, cb)
    },
})

let isClosing = false

// WebSocket server
wsServer.on('connection', function(ws) {

    const { user } = ws.upgradeReq

    console.log(`User ${user.username} connected`)
    pub.prefixedPublish('online', user.username)

    ws.on('message', function(messageText) {

      if (isClosing) {
        return
      }

      const message = JSON.parse(messageText)
      const { type, data } = message

      if (type === 'position') {

        console.log(`Position received from ${user.username}`)

        const { username } = user
        const { latitude, longitude } = data
        pub.prefixedPublish('tracking', JSON.stringify({
          user: username,
          coords: { lat: parseFloat(latitude), lng: parseFloat(longitude) }
        }))
      }

    })

    ws.on('close', function() {
      console.log(`User ${user.username} disconnected`)
      pub.prefixedPublish('offline', user.username)
    })

})

// Handle restarts
process.on('SIGINT', function () {

  console.log('------------------------')
  console.log('- STOPPING DISPATCH V2 -')
  console.log('------------------------')

  isClosing = true;

})
