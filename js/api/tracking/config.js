const Sequelize = require('sequelize')

module.exports = function(rootDir) {

  var sub = require('../RedisClient')({
    prefix: process.env.COOPCYCLE_DB_NAME + ':',
    url: process.env.COOPCYCLE_REDIS_DSN
  });

  var sequelize = new Sequelize(
    process.env.COOPCYCLE_DB.replace('sslmode=require', 'ssl=true'),
    {
      dialect: 'postgres',
      logging: false,
    }
  );

  return {
    sub,
    sequelize
  }
}
