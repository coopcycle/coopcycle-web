// ***********************************************************
// This example plugins/index.js can be used to load plugins
//
// You can change the location of this file or turn off loading
// the plugins file with the 'pluginsFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/plugins-guide
// ***********************************************************

// This function is called when a project is opened or re-opened (e.g. due to
// the project's config changing)

const wp = require('@cypress/webpack-preprocessor')

module.exports = (on, config) => {
  // `on` is used to hook into various events Cypress emits
  // `config` is the resolved Cypress config

  config.env.webpackFilename = 'webpack.cypress.js'

  const options = {
    webpackOptions: require('../../webpack.cypress'),
    watchOptions: {},
  }

  on('file:preprocessor', wp(options))

  // IMPORTANT to return the config object
  // with the changed environment variables
  return config
}
