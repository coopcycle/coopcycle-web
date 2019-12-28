const ConfigLoader = require('../ConfigLoader');
const Sequelize = require('sequelize')

module.exports = function(rootDir) {

  var envMap = {
    production: 'prod',
    development: 'dev',
    test: 'test'
  };

  try {

    var configFile = 'config.yml';
    if (envMap[process.env.NODE_ENV]) {
      configFile = 'config_' + envMap[process.env.NODE_ENV] + '.yml';
    }

    var configLoader = new ConfigLoader(rootDir + '/app/config/' + configFile);
    var config = configLoader.load();

  } catch (e) {
    throw e;
  }

  var sub = require('../RedisClient')({
    prefix: config.snc_redis.clients.default.options.prefix,
    url: config.snc_redis.clients.default.dsn
  });

  const port = parseInt(config.doctrine.dbal.port, 10)

  var sequelize = new Sequelize(
    config.doctrine.dbal.dbname,
    config.doctrine.dbal.user,
    config.doctrine.dbal.password,
    {
      host: config.doctrine.dbal.host,
      port: isNaN(port) ? 5432 : port,
      dialect: 'postgres',
      logging: false,
    }
  );

  return {
    sub,
    sequelize
  }
}
