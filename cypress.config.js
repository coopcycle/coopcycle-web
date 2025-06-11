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

  defaultCommandTimeout: 10000,

  retries: {
    // Configure retry attempts for `cypress run`
    runMode: 4,
    // Configure retry attempts for `cypress open`
    openMode: 0
  },

  e2e: {
    viewportWidth: 1920,
    viewportHeight: 1080,
    baseUrl: "http://localhost:9080",
    experimentalStudio: true,
    experimentalMemoryManagement: true,
    experimentalSourceRewriting: true
  },

  component: {
    viewportWidth: 1000,
    viewportHeight: 1000,
    devServer: {
      framework: 'react',
      bundler: 'webpack',
      // optionally pass in webpack config
      webpackConfig
    }
  }
})
