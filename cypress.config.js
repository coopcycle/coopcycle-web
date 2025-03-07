// override: false means that we won't override env vars from the command line
require('dotenv').config({override: false})

let env = process.env

module.exports = {
  chromeWebSecurity: false,

  env: {
    ...env,
    COMMAND_PREFIX: "docker compose exec -T php",
    coverage: false,
  },

  nodeVersion: "system",
  viewportWidth: 1600,

  retries: {
    // Configure retry attempts for `cypress run`
    runMode: 2,
    // Configure retry attempts for `cypress open`
    openMode: 0,
  },

  e2e: {
    // We've imported your old cypress plugins here.
    // You may want to clean this up later by importing these.
    setupNodeEvents(on, config) {
      return require("./cypress/plugins/index.js")(on, config);
    },
    baseUrl: "http://localhost:9080",
    experimentalStudio: true,
  },

  component: {
    setupNodeEvents(on, config) {},
    devServer: {
      framework: "react",
      bundler: "webpack",
    },
  },
};
