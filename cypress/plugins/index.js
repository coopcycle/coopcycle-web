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

require('dotenv').config()

module.exports = (on, config) => {
  // `on` is used to hook into various events Cypress emits
  // `config` is the resolved Cypress config

  config.env.ALGOLIA_PLACES_APP_ID = process.env.ALGOLIA_PLACES_APP_ID
  config.env.ALGOLIA_PLACES_API_KEY = process.env.ALGOLIA_PLACES_API_KEY
  config.env.GEOCODE_EARTH_API_KEY = process.env.GEOCODE_EARTH_API_KEY
  config.env.LOCATIONIQ_ACCESS_TOKEN = process.env.LOCATIONIQ_ACCESS_TOKEN

  // https://github.com/cypress-io/cypress/tree/master/npm/webpack-preprocessor#options
  // const options = {
  //   // send in the options from your webpack.config.js, so it works the same
  //   // as your app's code
  //   webpackOptions: require('../../webpack.cypress'),
  //   watchOptions: {},
  // }
  // on('file:preprocessor', webpackPreprocessor(options))

  // https://github.com/cypress-io/cypress/blob/master/npm/react/docs/recipes.md#your-webpack-config
  // from the root of the project (folder with cypress.json file)
  config.env.webpackFilename = 'webpack.cypress.js'
  require('@cypress/react/plugins/load-webpack')(on, config)

  // IMPORTANT to return the config object
  // with the any changed environment variables
  return config
}
