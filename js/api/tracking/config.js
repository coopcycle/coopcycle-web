const Sequelize = require('sequelize')

module.exports = function() {

  var sub = require('../RedisClient')({
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
      dialectOptions: { ssl: process.env.COOPCYCLE_POSTGRES_SSLMODE === 'require' }
    }
  );

  return {
    sub,
    sequelize
  }
}
