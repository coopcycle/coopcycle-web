var redis = require('redis');

const withPrefix = (prefix, channel) => prefix + channel

/*
 * We need this because the prefix is not applied to the `subscribe` command
 * See : https://github.com/NodeRedis/node_redis/issues/1286
 */

module.exports = function createRedisClient(options) {

  const redisClient = redis.createClient(options)

  redisClient.prefixedSubscribe = (channel) => redisClient.subscribe(withPrefix(options.prefix, channel))
  redisClient.prefixedPublish = (channel, message) => redisClient.publish(withPrefix(options.prefix, channel), message)
  redisClient.isChannel = (prefixed, unprefixed) => prefixed === (options.prefix + unprefixed)

  return redisClient;
};



