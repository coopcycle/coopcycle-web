var Encore = require('@symfony/webpack-encore')
var webpack = require('webpack')

Encore

  .setOutputPath(__dirname + '/web/build')
  .setPublicPath('/build')

  .addEntry('common', './js/app/common.js')
  .addEntry('customize-form', './js/app/customize/form.js')
  .addEntry('checkout-summary', './js/app/checkout/summary.js')
  .addEntry('dashboard', './js/app/dashboard/index.js')
  .addEntry('delivery-form', './js/app/delivery/form.js')
  .addEntry('delivery-list', './js/app/delivery/list.js')
  .addEntry('delivery-map', './js/app/delivery/map.js')
  .addEntry('delivery-embed-start-form', './js/app/delivery/embed-start.js')
  .addEntry('delivery-pricing-rules', './js/app/delivery/pricing-rules.js')
  .addEntry('delivery-tracking', './js/app/delivery/tracking.js')
  .addEntry('notifications', './js/app/notifications/index.js')
  .addEntry('foodtech-dashboard', './js/app/foodtech/dashboard/index.js')
  .addEntry('product-form', './js/app/product/form.js')
  .addEntry('product-list', './js/app/product/list.js')
  .addEntry('product-option-form', './js/app/forms/product-option.js')
  .addEntry('restaurant', './js/app/restaurant/index.js')
  .addEntry('restaurant-form', './js/app/restaurant/form.js')
  .addEntry('restaurant-list', './js/app/restaurant/list.js')
  .addEntry('restaurant-menu-editor', './js/app/restaurant/menu-editor.js')
  .addEntry('restaurant-planning', './js/app/restaurant/planning.js')
  .addEntry('restaurant-preparation-time', './js/app/restaurant/preparationTime.js')
  .addEntry('restaurants-map', './js/app/restaurants-map/index.js')
  .addEntry('search-address', './js/app/search/address.js')
  .addEntry('store-form', './js/app/store/form.js')
  .addEntry('user-tracking', './js/app/user/tracking.js')
  .addEntry('user-form', './js/app/user/form.js')
  .addEntry('widgets', './js/app/widgets/index.js')
  .addEntry('widgets-admin', './js/app/widgets/admin.js')
  .addEntry('zone-preview', './js/app/zone/preview.js')

  // @see https://symfony.com/doc/current/frontend/encore/custom-loaders-plugins.html#adding-custom-plugins
  // @see https://github.com/moment/moment/issues/2373
  // @see https://github.com/jmblog/how-to-optimize-momentjs-with-webpack
  .addPlugin(new webpack.ContextReplacementPlugin(
    /moment[/\\]locale$/,
    /ca|de|es|fr|pl|pt-br/
  ))

  .enableSingleRuntimeChunk()
  .splitEntryChunks()

  .enablePostCssLoader()
  .enableSassLoader(function(sassOptions) {}, {
    resolveUrlLoader: false
  })
  .enableLessLoader(function(lessOptions) {
    // Avoid error "Inline JavaScript is not enabled. Is it set in your options?"
    // https://github.com/ant-design/ant-motion/issues/44
    lessOptions.javascriptEnabled = true
  })

  .autoProvidejQuery()

  .enableVersioning(Encore.isProduction())

if (!Encore.isProduction()) {
  Encore.enableEslintLoader((options) => {
    options.rules = {
      'no-console': 'warn',
      'no-case-declarations': 'off',
      'no-extra-boolean-cast': 'off',
      'react/prop-types': 'off',
      'react/display-name': 'off',
    }
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
