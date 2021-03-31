const Sequelize = require('sequelize')

module.exports = function() {

  var redis = require('redis').createClient({
    prefix: process.env.COOPCYCLE_DB_NAME + ':',
    url: process.env.COOPCYCLE_REDIS_DSN
  });

  var sub = require('../RedisClient')({
    prefix: process.env.COOPCYCLE_DB_NAME + ':',
    url: process.env.COOPCYCLE_REDIS_DSN
  });

  var pub = require('../RedisClient')({
    prefix: process.env.COOPCYCLE_DB_NAME + ':',
    url: process.env.COOPCYCLE_REDIS_DSN
  });

  let port = 5432
  if (process.env.COOPCYCLE_DB_PORT) {
    port = parseInt(process.env.COOPCYCLE_DB_PORT, 10)
  }

  var sequelize = new Sequelize(
    process.env.COOPCYCLE_DB_NAME,
    process.env.COOPCYCLE_DB_USER,
    process.env.COOPCYCLE_DB_PASSWORD || null,
    {
      host: process.env.COOPCYCLE_DB_HOST,
      port: port,
      dialect: 'postgres',
      logging: false,
      dialectOptions: {
        ssl: {
          require: process.env.COOPCYCLE_POSTGRES_SSLMODE === 'require',
          rejectUnauthorized: false,
        }
      }
    }
  );

  return {
    redis,
    pub,
    sub,
    sequelize
  }
}
