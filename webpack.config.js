var Encore = require('@symfony/webpack-encore')
var webpack = require('webpack')

Encore

  .setOutputPath(__dirname + '/web/build')
  .setPublicPath('/build')

  .addEntry('common', './js/app/common.js')
  .addEntry('dashboard', './js/app/dashboard/index.jsx')
  .addEntry('delivery-form', './js/app/delivery/form.jsx')
  .addEntry('delivery-map', './js/app/delivery/map.js')
  .addEntry('delivery-pricing-rules', './js/app/delivery/pricing-rules.jsx')
  .addEntry('delivery-tracking', './js/app/delivery/tracking.js')
  .addEntry('notifications', './js/app/notifications/index.js')
  .addEntry('foodtech-dashboard', './js/app/foodtech/dashboard/index.js')
  .addEntry('product-form', './js/app/product/form.js')
  .addEntry('restaurant', './js/app/restaurant/index.js')
  .addEntry('restaurant-form', './js/app/restaurant/form.jsx')
  .addEntry('restaurant-menu-editor', './js/app/restaurant/menu-editor.js')
  .addEntry('restaurant-planning', './js/app/restaurant/planning.jsx')
  .addEntry('restaurant-preparation-time', './js/app/restaurant/preparationTime.js')
  .addEntry('restaurants-map', './js/app/restaurants-map/index.jsx')
  .addEntry('user-tracking', './js/app/user/tracking.jsx')
  .addEntry('user-form', './js/app/user/form.jsx')
  .addEntry('widgets', './js/app/widgets/index.js')
  .addEntry('widgets-admin', './js/app/widgets/admin.js')
  .addEntry('zone-preview', './js/app/zone/preview.jsx')

  // @see https://symfony.com/doc/current/frontend/encore/custom-loaders-plugins.html#adding-custom-plugins
  // @see https://github.com/moment/moment/issues/2373
  // @see https://github.com/jmblog/how-to-optimize-momentjs-with-webpack
  .addPlugin(new webpack.ContextReplacementPlugin(
    /moment[/\\]locale$/,
    /ca|de|es|fr/
  ))

  .enableSingleRuntimeChunk()
  .splitEntryChunks()

  .enablePostCssLoader()
  .enableSassLoader(function(sassOptions) {}, {
    resolveUrlLoader: false
  })

  .autoProvidejQuery()

  .enableVersioning(Encore.isProduction())

if (!Encore.isProduction()) {
  Encore
    .enableEslintLoader((options) => {
      options.envs = [
        'browser',
        'es6',
        'node',
      ]
      options.extends = [
        'eslint:recommended',
        'plugin:react/recommended'
      ]
      options.parserOptions = {
        ecmaFeatures: {
          jsx: true
        },
        ecmaVersion: 6
      }
      options.rules = {
        indent: [ 'error', 2 ],
        semi: [ 'error', 'never' ],
        'react/jsx-uses-react': 'error',
        'react/jsx-uses-vars': 'error',
      }
      options.plugins = [
        'react'
      ]
      options.globals = [
        'io',
        'CoopCycle',
        'Stripe',
        'google'
      ]
    })
}

let webpackConfig = Encore.getWebpackConfig()

webpackConfig.devServer = {
  headers: { 'Access-Control-Allow-Origin': '*' },
  stats: 'minimal',
  compress: true,
  watchOptions: {
    ignored: /node_modules/,
    poll: 1000
  }
}

webpackConfig.stats = {
  source: false,
}

module.exports = webpackConfig
