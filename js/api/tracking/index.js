var path = require('path');
var _ = require('lodash');
var http = require('http');
var winston = require('winston')
var redis = require('redis');

const TokenVerifier = require('../TokenVerifier')

const logger = winston.createLogger({
  level: process.env.NODE_ENV === 'production' ? 'info' : 'debug',
  format: process.env.NODE_ENV === 'production' ? winston.format.json() : winston.format.simple(),
  transports: [
    new winston.transports.Console({
      format: winston.format.simple()
    })
  ]
})

console.log('---------------------');
console.log('- STARTING TRACKING -');
console.log('---------------------');

console.log('NODE_ENV = ' + process.env.NODE_ENV);
console.log('NODE_APP_INSTANCE = ' + process.env.NODE_APP_INSTANCE)
console.log('PORT = ' + process.env.PORT);

const {
  sub,
  sequelize
} = require('./config')()

const tile38Sub = redis.createClient({ url: process.env.COOPCYCLE_TILE38_DSN })
const tile38Client = redis.createClient({ url: process.env.COOPCYCLE_TILE38_DSN })

const tile38ChannelName = `${process.env.COOPCYCLE_DB_NAME}:tracking`
const tile38FleetKey = `${process.env.COOPCYCLE_DB_NAME}:fleet`

const db = require('../Db')(sequelize)

const server = http.createServer(function(request, response) {
    // process HTTP request. Since we're writing just WebSockets server
    // we don't have to implement anything.
});
const tokenVerifier = new TokenVerifier(process.env.COOPCYCLE_PUBLIC_KEY_FILE, db)

const io = require('socket.io')(server, { path: '/tracking/socket.io' });

function createTile38Channel(tile38ChannelName, tile38FleetKey) {

  // We create bounds that cover the whole world
  // SETCHAN tracking WITHIN fleet FENCE BOUNDS -90 -180 90 180
  return new Promise((resolve, reject) => {
    tile38Client.send_command('SETCHAN', [tile38ChannelName, 'WITHIN', tile38FleetKey, 'FENCE', 'BOUNDS', -90, -180, 90, 180], function(err, res) {
      if (!err) {
        resolve()
      } else {
        reject()
      }
    })
  })
}

function bootstrap() {

  const subscribeToRedis = () => new Promise((resolve, reject) => {
    sub.on('psubscribe', (channel, count) => {
      logger.info(`Subscribed to ${channel} (${count})`)
      if (count === 3) {
        resolve()
      }
    })
    sub.prefixedPSubscribe('users:*')
    sub.prefixedPSubscribe('couriers:*')
    sub.prefixedPSubscribe('orders:*')
  })

  const createTile38ChannelIfNotExists = () => new Promise((resolve, reject) => {
    tile38Client.send_command('CHANS', ['*'], function(err, res) {
      if (!err) {
        const tile38ChannelWithSameName = _.find(res, (item) => item[0] === tile38ChannelName)
        if (tile38ChannelWithSameName) {
          if (tile38ChannelWithSameName[1] === tile38FleetKey) {
            resolve()
          } else {
            tile38Client.send_command('DELCHAN', [tile38ChannelName], function(err, res) {
              if (!err) {
                createTile38Channel(tile38ChannelName, tile38FleetKey)
                  .then(resolve)
                  .catch(reject)
              } else {
                reject()
              }
            })
          }
          return
        }

        createTile38Channel(tile38ChannelName, tile38FleetKey)
          .then(resolve)
          .catch(reject)

      } else {
        reject()
      }
    })
  })

  const subscribeToTile38 = () => new Promise((resolve, reject) => {
    createTile38ChannelIfNotExists()
      .then(() => {
        tile38Sub.on('subscribe', function(channel, count) {
          if (count === 1) {
            resolve()
          }
        })
        tile38Sub.send_command('SUBSCRIBE', [tile38ChannelName])
      })
  })

  return Promise.all([ subscribeToRedis(), subscribeToTile38() ])
}

bootstrap()
  .then(initialize)

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
          if (user.hasOwnProperty('order')) {
            socket.order = user.order
          }
        }
        next();
      })
      .catch(e => next(new Error('Authentication error')))

  } else {
    next(new Error('Authentication error'));
  }
}

// @see https://github.com/NodeRedis/node-redis/blob/master/examples/scan.js

let cursor = 0

function scan () {

  logger.info('Scanning…')

  tile38Client.send_command('SCAN', [tile38FleetKey, 'CURSOR', cursor, 'LIMIT', '10'], function (err, res) {

    if (err) throw err;

    cursor = res[0];
    const keys = res[1]

    // Remember: more or less than COUNT or no keys may be returned
    // See http://redis.io/commands/scan#the-count-option
    // Also, SCAN may return the same key multiple times
    // See http://redis.io/commands/scan#scan-guarantees
    // Additionally, you should always have the code that uses the keys
    // before the code checking the cursor.
    if (keys.length > 0) {
      keys.forEach(function(key) {
        const [ username, data ] = key
        const object = JSON.parse(data)
        const [ lng, lat, timestamp ] = object.coordinates
        io.in('dispatch').emit('tracking', {
          user: username,
          coords: { lat, lng },
          ts: timestamp,
        })
      })
    }

    // It's important to note that the cursor and returned keys
    // vary independently. The scan is never complete until redis
    // returns a non-zero cursor. However, with MATCH and large
    // collections, most iterations will return an empty keys array.

    // Still, a cursor of zero DOES NOT mean that there are no keys.
    // A zero cursor just means that the SCAN is complete, but there
    // might be one last batch of results to process.

    // From <http://redis.io/commands/scan>:
    // 'An iteration starts when the cursor is set to 0,
    // and terminates when the cursor returned by the server is 0.'
    if (cursor === 0) {
      return;
    }

    return scan();
  })
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

  tile38Sub.on('message', function(channel, message) {

    // {
    //   "command":"set",
    //   "group":"5e55a38afdee2e00017205c2",
    //   "detect":"inside",
    //   "hook":"tracking",
    //   "key":"fleet",
    //   "time":"2020-02-26T13:53:48.2944184Z",
    //   "id":"truck1",
    //   "object":{
    //     "type":"Point",
    //     "coordinates":[
    //       -12.2693,
    //       3.5123
    //     ]
    //   }
    // }

    const data = JSON.parse(message)

    if (data.command !== 'set') {
      return
    }

    if (data.object && data.object.type && data.object.type === 'Point') {

      logger.info(`Sending "tracking" message to users in rooms "admins" & "couriers:${data.id}"`)

      const [ lng, lat, timestamp ] = data.object.coordinates
      io.in('dispatch').emit('tracking', {
        user: data.id,
        coords: { lat, lng },
        ts: timestamp,
      })
      io.in(`couriers:${data.id}`).emit('tracking', {
        user: data.id,
        coords: { lat, lng },
        ts: timestamp,
      })
    }

  })

  io
    .use(authMiddleware)
    .on('connect', function (socket) {
      if (socket.user) {
        socket.join(`users:${socket.user.username}`, (err) => {
          if (!err) {
            logger.info(`user "${socket.user.username}" joined room "users:${socket.user.username}"`)
          }
        })
        // This is a dispatcher
        if (_.includes(socket.user.roles, 'ROLE_ADMIN')) {
          socket.join('dispatch', (err) => {
            if (!err) {
              logger.info(`user "${socket.user.username}" joined room "dispatch"`)
              scan()
            }
          })
        }
      } else {
        if (socket.courier) {
          socket.join(`couriers:${socket.courier}`, (err) => {
            if (!err) {
              logger.info(`user joined room "couriers:${socket.courier}"`)
            }
          })
        }
        if (socket.order) {
          socket.join(`orders:${socket.order}`, (err) => {
            if (!err) {
              logger.info(`user joined room "orders:${socket.order}"`)
            }
          })
        }
      }
    })

  server.listen(process.env.PORT || 8001);

}
