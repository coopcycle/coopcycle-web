var Encore = require('@symfony/webpack-encore')

Encore

  .setOutputPath(__dirname + '/web')
  .setPublicPath('/')

  .addStyleEntry('css/dashboard', './assets/css/dashboard.scss')

  .addEntry('js/common', './js/app/common.js')
  .addEntry('js/dashboard', './js/app/dashboard/index.jsx')
  .addEntry('js/delivery-form', './js/app/delivery/form.jsx')
  .addEntry('js/delivery-list', './js/app/delivery/list.jsx')
  .addEntry('js/delivery-pricing-rules', './js/app/delivery/pricing-rules.jsx')
  .addEntry('js/notifications', './js/app/notifications/index.js')
  .addEntry('js/foodtech-dashboard', './js/app/foodtech/dashboard/index.js')
  .addEntry('js/restaurant', './js/app/restaurant/index.js')
  .addEntry('js/restaurant-form', './js/app/restaurant/form.jsx')
  .addEntry('js/restaurant-menu-editor', './js/app/restaurant/menu-editor.js')
  .addEntry('js/restaurant-planning', './js/app/restaurant/planning.jsx')
  .addEntry('js/restaurant-preparation-time', './js/app/restaurant/preparationTime.js')
  .addEntry('js/restaurants-map', './js/app/restaurants-map/index.jsx')
  .addEntry('js/task-modal', './js/app/task/modal.js')
  .addEntry('js/user-tracking', './js/app/user/tracking.jsx')
  .addEntry('js/user-form', './js/app/user/form.jsx')
  .addEntry('js/widgets', './js/app/widgets/index.js')
  .addEntry('js/zone-preview', './js/app/zone/preview.jsx')

  .enableSingleRuntimeChunk()
  .splitEntryChunks()

  .enablePostCssLoader()
  .enableSassLoader(function(sassOptions) {}, {
    resolveUrlLoader: false
  })

  .configureFilenames({
    images: 'img/build/[name].[hash:8].[ext]'
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
