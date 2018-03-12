const _ = require('lodash')
const Courier = require('../models/Courier')
const Delivery = require('../models/Delivery')
const Utils = require('../Utils')
const moment = require('moment')

module.exports = function(db, redis, pub, metrics, couriers, deliveries, winston) {

  const preload = () => {

    const { Address, Order, Restaurant, User } = db
    const params = {
      where: {
        status: { $in: [ Delivery.WAITING ] },
      },
      include: [
        { model: Order, as: 'order' },
        { model: User, as: 'courier' },
        { model: Address, as: 'originAddress' },
        { model: Address, as: 'deliveryAddress' }
      ]
    }

    return new Promise(function(resolve, reject) {
      db.Delivery
        .findAll(params)
        .then(function(results) {

        // winston.info('Found ' + orders.length + ' orders to manage')
        const identifiers = _.map(results, delivery => delivery.id)

        let keys = [
          'deliveries:waiting',
          'deliveries:dispatching',
        ];

        redis.del(keys, (err, value) => {
          if (identifiers.length > 0) {
            redis.rpush('deliveries:waiting', identifiers, function(err) {
              resolve()
            });
          } else {
            resolve()
          }
        })
      });
    });
  };

  const nextDelivery = (registry) => {
    return new Promise((resolve, reject) => {
      redis.brpoplpush('deliveries:waiting', 'deliveries:waiting', 0, function(err, id) {
        deliveries.findById(id).then(delivery => resolve(delivery))
      })
    })
  }

  const removeFromWaiting = (id) => {
    winston.debug(`Removing delivery ${id} from waiting list`)
    return new Promise((resolve, reject) => {
      redis.lrem('deliveries:waiting', 0, id, function(err, value) {
        resolve(value)
      })
    })
  }

  const addToWaiting = (id) => {
    winston.debug(`Adding delivery ${id} to waiting list`)
    return new Promise((resolve, reject) => {
      redis.lpush('deliveries:waiting', id, function(err, value) {
        resolve(value)
      })
    })
  }

  const removeFromDispatching = (id) => {
    winston.debug(`Removing delivery ${id} from dispatching list`)
    return new Promise((resolve, reject) => {
      redis.lrem('deliveries:dispatching', 0, id, function(err, value) {
        resolve(value)
      })
    })
  }

  const addToDispatching = (id) => {
    winston.debug(`Adding delivery ${id} to dispatching list`)
    return new Promise((resolve, reject) => {
      redis.lpush('deliveries:dispatching', id, function(err, value) {
        resolve(value)
      })
    })
  }

  /**
   * Find the closest available courier for a given delivery.
   * @param Delivery delivery The handled delivery
   * @param int distance The radius from the customer position in which we are looking for a courier
   */
  const findNearest = (delivery, distance = 3500) => {

    const { latitude, longitude } = delivery.originAddress.position;

    return new Promise(function(resolve, reject) {

      // Returns all the couriers which are in distance from the restaurant address
      redis.georadius('couriers:geo', longitude, latitude, distance, "m", 'WITHDIST', 'ASC', function(err, matches) {

        if (!matches) {
          return resolve(null);
        }

        // Filter couriers :
        //  - courier that are available
        //  - courier that didn't already refuse the delivery
        var results = _.filter(matches, (match) => {
          var key = match[0];
          var courier = couriers.findByKey(key);
          if (!courier) {
            // console.log('Courier ' + key + ' not found in pool');
            return false;
          }

          return courier.isAvailable() && !courier.hasDeclinedDelivery(delivery.id);
        });

        if (results.length === 0) {
          return resolve(null);
        }

        // console.log('There are ' + results.length + ' couriers available');

        // Return nearest courier
        var first = results[0];
        var key = first[0];

        return resolve(couriers.findByKey(key));
      });
    });
  }

  const onConnect = (courier, ws) => {

    courier.connect(ws)
    couriers.add(courier)

    metrics.gauge('couriers.connected', couriers.size())

    winston.info(`Courier ${courier.username} connected!`)
    pub.prefixedPublish('online', courier.username)

  }

  const onUpdateCoordinates = (courier, coordinates) => {

    // winston.debug('Courier ' + courier.username + ', state = ' + courier.state + ' updating position in Redis...');

    if (courier.state === Courier.UNKNOWN) {
      winston.debug('Position received!');
      courier.setState(Courier.AVAILABLE);
    }

    redis.geoadd('couriers:geo',
      coordinates.longitude,
      coordinates.latitude,
      Utils.resolveKey('courier', courier.id)
    );

    redis.rpush('tracking:' + courier.username, JSON.stringify({
      ...coordinates,
      timestamp: moment().unix()
    }))

    const { username } = courier
    const { latitude, longitude } = coordinates

    pub.prefixedPublish('tracking', JSON.stringify({
      user: username,
      coords: { lat: parseFloat(latitude), lng: parseFloat(longitude) }
    }))

  }

  const subscribe = (sub) => {

    sub.prefixedSubscribe('couriers')
    sub.prefixedSubscribe('couriers:available')
    sub.prefixedSubscribe('deliveries:declined')

    sub.on('message', function(channel, message) {

      if (sub.isChannel(channel, 'couriers')) {
        console.log('Courier #' + message + ' has accepted delivery');
        var courier = couriers.findById(message);
        if (courier) {
          courier.setState('DELIVERING');
        }
      }

      if (sub.isChannel(channel, 'couriers:available')) {
        console.log('Courier #' + message + ' is available again');
        var courier = couriers.findById(message);
        if (courier) {
          courier.setState('AVAILABLE');
        }
      }

      if (sub.isChannel(channel, 'deliveries:declined')) {

        console.log('Courier #' + data.courier + ' has declined delivery #' + data.delivery);
        var data = JSON.parse(message);
        var courier = couriers.findById(data.courier);

        if (courier.delivery !== data.delivery) {
          console.log('Delivery #' + delivery + ' was not dispatched to courier #' + this.id);
          return;
        }

        removeFromDispatching(data.delivery)
          .then(() => addToWaiting(data.delivery))
          .then(() => {
            courier.delivery = null
            courier.setState(Courier.UNKNOWN)
            courier.declineDelivery(delivery)
          })
      }

    })
  }

  return {
    addToDispatching,
    addToWaiting,
    findNearest,
    nextDelivery,
    onConnect,
    onUpdateCoordinates,
    preload,
    removeFromDispatching,
    removeFromWaiting,
    subscribe,
  }
}
