require('dotenv').config()

var argv = require('minimist')(process.argv.slice(2));
var _ = require('lodash');
var ConfigLoader = require('./js/api/ConfigLoader');

var watchOptions = {
  usePolling: true,
  ignorePermissionErrors: true,
  followSymlinks: false,
  interval: 400
};

var env = argv.env || 'development';

var ROOT_DIR = __dirname;

var envMap = {
  production: 'prod',
  development: 'dev',
  test: 'test'
};

try {
  var configFile = 'config.yml';
  if (envMap[env]) {
    configFile = 'config_' + envMap[env] + '.yml';
    console.log('Config file : ' + configFile);
  } else {
    console.log('No config file loaded');
  }
  var configLoader = new ConfigLoader(ROOT_DIR + '/app/config/' + configFile);
  var config = configLoader.load();
} catch (e) {
  throw e;
}

var appName = config.parameters['app.name'] || 'default';

var apps = [{
  name: "coopcycle-dispatch-" + appName,
  mergeLogs: true,
  script: "./js/api/dispatch/index.js",
  watch: ["./js/api/dispatch/index.js", "./js/api/models/*.js", "./js/api/*.js"],
  port: config.parameters['app.dispatch_port'] || 8000
}, {
  name: "coopcycle-tracking-" + appName,
  mergeLogs: true,
  script: "./js/api/tracking/index.js",
  watch: ["./js/api/tracking/index.js", "./js/api/tracking/index.html"],
  port: config.parameters['app.tracking_port'] || 8001
}];

apps = _.map(apps, function(app) {
  if (env === 'production') {
    delete app.watch;
    app = _.extend(app, {
      cwd: __dirname,
    });
  } else {
    app = _.extend(app, {
      watch_options: watchOptions
    });
  }

  return _.extend(app, {
    env: {
      NODE_ENV: "development",
      PORT: app.port,
      COOPCYCLE_DB_HOST:     process.env.COOPCYCLE_DB_HOST,
      COOPCYCLE_DB_NAME:     process.env.COOPCYCLE_DB_NAME,
      COOPCYCLE_DB_USER:     process.env.COOPCYCLE_DB_USER,
      COOPCYCLE_DB_PASSWORD: process.env.COOPCYCLE_DB_PASSWORD,
      COOPCYCLE_REDIS_DSN:   process.env.COOPCYCLE_REDIS_DSN,
    },
    env_production : {
      NODE_ENV: "production",
      PORT: app.port,
    },
    env_test : {
      NODE_ENV: "test",
      PORT: app.port,
      COOPCYCLE_DB_NAME:     process.env.COOPCYCLE_DB_NAME + '_test',
    }
  });
});

module.exports = {
  apps: apps
};
