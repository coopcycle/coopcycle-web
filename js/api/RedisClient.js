var redis = require('redis');


/*
 * We need this because the prefix is not applied to the `subscribe` command
 * See : https://github.com/NodeRedis/node_redis/issues/1286
 */

module.exports =  function createRedisClient(options) {

  redisClient = redis.createClient(options);

  redisClient.prefixedSubscribe = function (channel) {
    channel = options.prefix + channel;
    return this.subscribe(channel);
  };

  return redisClient;
};



