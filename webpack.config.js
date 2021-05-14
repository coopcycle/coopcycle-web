var Encore = require('@symfony/webpack-encore')
var webpack = require('webpack')
var path = require('path')
var ESLintPlugin = require('eslint-webpack-plugin')

Encore

  .setOutputPath(__dirname + '/web/build')

  .setPublicPath('/build')

  // Use this if you want to debug on a real device
  // .setPublicPath('http://192.168.0.11:8080')
  // .setManifestKeyPrefix('/build')

  .addEntry('admin-orders', './js/app/admin/orders.js')
  .addEntry('admin-restaurants', './js/app/admin/restaurants.js')
  .addEntry('common', './js/app/common.js')
  .addEntry('customize-form', './js/app/customize/form.js')
  .addEntry('checkout-summary', './js/app/checkout/summary.js')
  .addEntry('dashboard', './js/app/dashboard/index.js')
  .addEntry('delivery-form', './js/app/delivery/form.js')
  .addEntry('delivery-homepage', './js/app/delivery/homepage.js')
  .addEntry('delivery-list', './js/app/delivery/list.js')
  .addEntry('delivery-map', './js/app/delivery/map.js')
  .addEntry('delivery-embed-start-form', './js/app/delivery/embed-start.js')
  .addEntry('delivery-pricing-rules', './js/app/delivery/pricing-rules.js')
  .addEntry('delivery-tracking', './js/app/delivery/tracking.js')
  .addEntry('notifications', './js/app/notifications/index.js')
  .addEntry('foodtech-dashboard', './js/app/foodtech/dashboard/index.js')
  .addEntry('metrics', './js/app/metrics/index.js')
  .addEntry('metrics-admin', './js/app/metrics/admin.js')
  .addEntry('order', './js/app/order/index.js')
  .addEntry('product-form', './js/app/product/form.js')
  .addEntry('product-list', './js/app/product/list.js')
  .addEntry('product-option-form', './js/app/forms/product-option.js')
  .addEntry('register', './js/app/register/index.js')
  .addEntry('restaurant', './js/app/restaurant/index.js')
  .addEntry('restaurant-form', './js/app/restaurant/form.js')
  .addEntry('restaurant-fulfillment-methods', './js/app/restaurant/fulfillment-methods.js')
  .addEntry('restaurant-list', './js/app/restaurant/list.js')
  .addEntry('restaurant-menu-editor', './js/app/restaurant/menu-editor.js')
  .addEntry('restaurant-planning', './js/app/restaurant/planning.js')
  .addEntry('restaurant-preparation-time', './js/app/restaurant/preparationTime.js')
  .addEntry('restaurants-map', './js/app/restaurants-map/index.js')
  .addEntry('search-address', './js/app/search/address.js')
  .addEntry('search-user', './js/app/search/user.js')
  .addEntry('store-form', './js/app/store/form.js')
  .addEntry('task-list', './js/app/delivery/task-list.js')
  .addEntry('time-slot-form', './js/app/time-slot/form.js')
  .addEntry('user-tracking', './js/app/user/tracking.js')
  .addEntry('user-form', './js/app/user/form.js')
  .addEntry('user-invite', './js/app/user/invite.js')
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
  .enableSassLoader(function(sassLoaderOptions) {
    // https://github.com/twbs/bootstrap-sass#sass-number-precision
    if (sassLoaderOptions.sassOptions) {
      sassLoaderOptions.sassOptions.precision = 8
    } else {
      sassLoaderOptions.sassOptions = { precision: 8 }
    }
  }, {
    resolveUrlLoader: false
  })
  .enableLessLoader(function(lessLoaderOptions) {
    // Avoid error "Inline JavaScript is not enabled. Is it set in your options?"
    // https://github.com/ant-design/ant-motion/issues/44
    if (lessLoaderOptions.lessOptions) {
      lessLoaderOptions.lessOptions.javascriptEnabled = true
    } else {
      lessLoaderOptions.lessOptions = { javascriptEnabled: true }
    }
  })

  .autoProvidejQuery()

  .enableVersioning(Encore.isProduction())

if (!Encore.isProduction()) {
  // https://github.com/symfony/webpack-encore/issues/847
  Encore.addPlugin(new ESLintPlugin())
}

// https://github.com/webpack/webpack-dev-server/blob/master/CHANGELOG.md#400-beta0-2020-11-27
Encore.configureDevServerOptions(options => {
  options.firewall = false
  options.static = [
    {
      directory: 'web/',
      watch: {
        usePolling: true,
      }
    }
  ]
  options.headers = { 'Access-Control-Allow-Origin': '*' }
  options.compress = true
})

let webpackConfig = Encore.getWebpackConfig()

webpackConfig.stats = 'minimal'

module.exports = webpackConfig
