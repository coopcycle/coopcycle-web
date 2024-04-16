module.exports = {
  chromeWebSecurity: false,
  env: {
    COMMAND_PREFIX: 'docker compose exec -T php',
    coverage: false,
    STRIPE_CONNECT_CLIENT_ID: '',
    STRIPE_SECRET_KEY: '',
    STRIPE_PUBLISHABLE_KEY: ''
  },
  nodeVersion: 'system',
  viewportWidth: 1600,
  e2e: {
    // We've imported your old cypress plugins here.
    // You may want to clean this up later by importing these.
    setupNodeEvents(on, config) {
      return require('./cypress/plugins/index.js')(on, config)
    },
    baseUrl: 'http://localhost:9080',
  },
  component: {
    setupNodeEvents(on, config) {},
  },
}
