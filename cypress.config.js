module.exports = {
  "chromeWebSecurity": false,
  "env": {
    "COMMAND_PREFIX": "docker-compose exec -T php",
    "DEBUG": "cypress:*",
    "coverage": false
  },
  "nodeVersion": "system",
  e2e: {
    // We've imported your old cypress plugins here.
    // You may want to clean this up later by importing these.
    setupNodeEvents(on, config) {
      return require('./cypress/plugins/index.js')(on, config);
    },
    "baseUrl": "http://localhost:9080"
  },
  component: {
    setupNodeEvents(on, config) {}

  }
};