var Encore = require('@symfony/webpack-encore')
var CopyWebpackPlugin = require('copy-webpack-plugin')

Encore

  .setOutputPath(__dirname + '/web')
  .setPublicPath('/')

  .addStyleEntry('css/dashboard', './assets/css/dashboard.scss')
  .addStyleEntry('css/styles', './assets/css/main.scss')

  .createSharedEntry('js/vendor', [ 'jquery', 'bootstrap', 'es6-set', 'whatwg-fetch' ])
  .addEntry('js/common', './js/app/common.js')

  .addEntry('js/dashboard', './js/app/dashboard/index.jsx')
  .addEntry('js/homepage', './js/app/homepage/index.js')
  .addEntry('js/restaurant-list', './js/app/restaurant-list/index.jsx')
  .addEntry('js/cart', './js/app/cart/index.jsx')
  .addEntry('js/order', './js/app/order/index.jsx')
  .addEntry('js/order-tracking', './js/app/order/tracking.jsx')
  .addEntry('js/delivery-form', './js/app/delivery/form.jsx')
  .addEntry('js/delivery-list', './js/app/delivery/list.jsx')
  .addEntry('js/delivery-pricing-rules', './js/app/delivery/pricing-rules.jsx')
  .addEntry('js/restaurant-form', './js/app/restaurant/form.jsx')
  .addEntry('js/restaurant-menu', './js/app/restaurant/menu.jsx')
  .addEntry('js/restaurant-planning', './js/app/restaurant/planning.jsx')
  .addEntry('js/restaurant-panel', './js/app/restaurant/panel.jsx')
  .addEntry('js/restaurants-map', './js/app/restaurants-map/index.jsx')
  .addEntry('js/task-modal', './js/app/task/modal.js')
  .addEntry('js/user-tracking', './js/app/user/tracking.jsx')
  .addEntry('js/user-form', './js/app/user/form.jsx')
  .addEntry('js/widgets', './js/app/widgets/index.js')
  .addEntry('js/zone-preview', './js/app/zone/preview.jsx')

  .enablePostCssLoader()
  .enableSassLoader(function(sassOptions) {}, {
    resolveUrlLoader: false
  })

  .configureFilenames({
    images: 'img/build/[name].[hash:8].[ext]'
  })

  .enableReactPreset()
  .autoProvidejQuery()

  .enableVersioning(Encore.isProduction())

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
webpackConfig.plugins.push(new CopyWebpackPlugin([
  {
    from: 'node_modules/coopcycle-js/build/coopcycle.js',
    to: 'js/coopcycle.js'
  }
]))

module.exports = webpackConfig
