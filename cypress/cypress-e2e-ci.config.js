// override: false means that we won't override env vars from the command line
require('dotenv').config({ override: false })
const { defineConfig } = require('cypress')

const env = process.env

module.exports = defineConfig({
  chromeWebSecurity: false,

  env: {
    ...env,
    COMMAND_PREFIX: 'docker compose exec -T php',
    coverage: false,
  },

  defaultCommandTimeout: 10000,

  retries: {
    // Configure retry attempts for `cypress run`
    runMode: 4,
  },

  e2e: {
    supportFile: 'support/e2e.{js,jsx,ts,tsx}',
    viewportWidth: 1920,
    viewportHeight: 1080,
    baseUrl: 'http://localhost:9080',
    experimentalStudio: true,
    experimentalMemoryManagement: true,
    experimentalSourceRewriting: true,
  },
})
