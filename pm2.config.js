require('dotenv').config()

var argv = require('minimist')(process.argv.slice(2));
var _ = require('lodash');

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

var appName = process.env.COOPCYCLE_APP_NAME || 'default';

var apps = [{
  name: "coopcycle-dispatch-" + appName,
  mergeLogs: true,
  log_type : 'json',
  script: "./js/api/dispatch/index.js",
  watch: ["./js/api/dispatch/index.js", "./js/api/models/*.js", "./js/api/*.js"],
  port: (process.env.COOPCYCLE_DISPATCH_PORT && parseInt(process.env.COOPCYCLE_DISPATCH_PORT, 10)) || 8000
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
      NODE_PATH: process.env.NODE_PATH,
      PORT: app.port,
      COOPCYCLE_DB_HOST:     process.env.COOPCYCLE_DB_HOST,
      COOPCYCLE_DB_NAME:     process.env.COOPCYCLE_DB_NAME,
      COOPCYCLE_DB_USER:     process.env.COOPCYCLE_DB_USER,
      COOPCYCLE_DB_PASSWORD: process.env.COOPCYCLE_DB_PASSWORD,
      COOPCYCLE_REDIS_DSN:   process.env.COOPCYCLE_REDIS_DSN,
      COOPCYCLE_POSTGRES_SSLMODE: process.env.COOPCYCLE_POSTGRES_SSLMODE,
      COOPCYCLE_TILE38_DSN:   process.env.COOPCYCLE_TILE38_DSN,
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
