var argv = require('minimist')(process.argv.slice(2));
var _ = require('underscore');
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
  script: "./js/api/dispatch/index.js",
  watch: ["./js/api/dispatch/index.js", "./js/api/models/*.js", "./js/api/*.js"],
  port: config.parameters['app.dispatch_port'] || 8000
}, {
  name: "coopcycle-tracking-" + appName,
  script: "./js/api/tracking/index.js",
  watch: ["./js/api/tracking/index.js", "./js/api/tracking/index.html"],
  port: config.parameters['app.tracking_port'] || 8001
}, {
  name: "coopcycle-order-tracking-" + appName,
  script: "./js/api/order/tracking.js",
  watch: ["./js/api/order/tracking.js"],
  port: config.parameters['app.order_tracking_port'] || 8002
}, {
  name: "coopcycle-restaurant-panel-" + appName,
  script: "./js/api/restaurant/panel.js",
  watch: ["./js/api/restaurant/panel.js"],
  port: config.parameters['app.restaurant_panel_port'] || 8003
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
      ASSETS_BASE_URL: config.framework.assets.base_urls ? config.framework.assets.base_urls['0'] : ''
    },
    env_production : {
      NODE_ENV: "production",
      PORT: app.port,
      ASSETS_BASE_URL: ''
    },
    env_test : {
      NODE_ENV: "test",
      PORT: app.port,
      ASSETS_BASE_URL: config.framework.assets.base_urls ? config.framework.assets.base_urls['0'] : ''
    }
  });
});

module.exports = {
  apps: apps
};
