// override: false means that we won't override env vars from the command line
require('dotenv').config({ override: false })
const { defineConfig } = require('cypress')
const webpackConfig = require('./webpack.cypress.js')

const env = process.env

module.exports = defineConfig({
  chromeWebSecurity: false,

  env: {
    ...env,
    // Run as www-data, the user PHP-FPM serves requests as. As root, any log
    // file a fixture command creates is owned by root, and PHP-FPM can then no
    // longer append to it -- every subsequent page returns a 500.
    COMMAND_PREFIX: 'docker compose exec -u www-data -T php',
    coverage: false,
  },

  defaultCommandTimeout: 10000,

  retries: {
    // Configure retry attempts for `cypress run`
    runMode: 4,
    // Configure retry attempts for `cypress open`
    openMode: 0,
  },

  e2e: {
    viewportWidth: 1920,
    viewportHeight: 1080,
    baseUrl: 'http://localhost:9080',
    experimentalStudio: true,
    experimentalMemoryManagement: true,
    experimentalSourceRewriting: true,
  },

  component: {
    viewportWidth: 1000,
    viewportHeight: 1000,
    devServer: {
      framework: 'react',
      bundler: 'webpack',
      // optionally pass in webpack config
      webpackConfig,
    },
  },
})
