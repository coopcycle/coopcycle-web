// override: false means that we won't override env vars from the command line
require('dotenv').config({override: false})
const { defineConfig } = require('cypress')
const webpackConfig = require('./webpack.cypress.js')
const env = process.env

module.exports = defineConfig({
  chromeWebSecurity: false,

  env: {
    ...env,
    COMMAND_PREFIX: "docker compose exec -T php",
    coverage: false
  },

  viewportWidth: 1600,

  defaultCommandTimeout: 10000,

  retries: {
    // Configure retry attempts for `cypress run`
    runMode: 4,
    // Configure retry attempts for `cypress open`
    openMode: 0
  },

  e2e: {
    baseUrl: "http://localhost:9080",
    experimentalStudio: true,
    experimentalMemoryManagement: true,
    experimentalSourceRewriting: true
  },

  component: {
    devServer: {
      framework: 'react',
      bundler: 'webpack',
      // optionally pass in webpack config
      webpackConfig
    }
  }
})
