var Encore = require('@symfony/webpack-encore')
var webpack = require('webpack')
var path = require('path')
var ESLintPlugin = require('eslint-webpack-plugin')
const AntdMomentWebpackPlugin = require('@ant-design/moment-webpack-plugin');

if (!Encore.isRuntimeEnvironmentConfigured()) {
  Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore

  .setOutputPath(__dirname + '/web/build')

  .setPublicPath('/build')

  // Use this if you want to debug on a real device
  // .setPublicPath('http://192.168.0.11:8080')
  // .setManifestKeyPrefix('/build')

  .addEntry('app', './assets/app.js')
  .addEntry('adhoc-order', './js/app/adhoc_order/index.js')
  .addEntry('admin-cube', './js/app/admin/cube.js')
  .addEntry('admin-orders', './js/app/admin/orders.js')
  .addEntry('admin-restaurants', './js/app/admin/restaurants.js')
  .addEntry('admin-foodtech-dashboard', './js/app/admin/foodtech/dashboard.js')
  .addEntry('admin-version', './js/app/admin/version.js')
  .addEntry('business-account', './js/app/business-account/index.js')
  .addEntry('common', './js/app/common.js')
  .addEntry('customize-form', './js/app/customize/form.js')
  .addEntry('customize-shop-collection', './js/app/customize/shop-collection.js')
  .addEntry('dashboard', './js/app/dashboard/index.js')
  .addEntry('datadog', './js/app/datadog.ts')
  .addEntry('delivery-form', './js/app/delivery/form.js')
  .addEntry('delivery-homepage', './js/app/delivery/homepage.js')
  .addEntry('delivery-list', './js/app/delivery/list.js')
  .addEntry('delivery-map', './js/app/delivery/map.js')
  .addEntry('delivery-embed-start-form', './js/app/delivery/embed-start.js')
  .addEntry('delivery-pricing-rules', './js/app/delivery/pricing/pricing-rules.js')
  .addEntry('pricing-rule-set-form-react', './js/app/admin/pricing/entrypoint.tsx')
  .addEntry('delivery-tracking', './js/app/delivery/tracking.js')
  .addEntry('delivery-form-react', './js/app/store/deliveries/entrypoint.tsx')
  .addEntry('recurrence-rule-form-react', './js/app/store/recurrence_rules/entrypoint.tsx')
  .addEntry('invoicing', './js/app/admin/invoicing/entrypoint.tsx')
  .addEntry('notifications', './js/app/notifications/index.js')
  .addEntry('foodtech-dashboard', './js/app/foodtech/dashboard/index.js')
  .addEntry('metrics', './js/app/metrics/index.js')
  .addEntry('metrics-admin', './js/app/metrics/admin.js')
  .addEntry('metrics-loopeat', './js/app/metrics/loopeat.js')
  .addEntry('optins', './js/app/optins/index.js')
  // FoodTech checkout; "Address" page (path: '/order/')
  .addEntry('order-index', './js/app/order/index.js')
  // FoodTech checkout; "Payment" page (path: '/order/payment/')
  .addEntry('order-payment', './js/app/order/payment/index.js')
  // FoodTech checkout; "Confirmation" page (path: '/order/confirm/{hashid}')
  // Profile > Orders (only FoodTech orders) (path: '/profile/orders/{id}')
  .addEntry('order-details', './js/app/order/details/index.js')
  // Dispatcher: Order page (path: '/admin/orders/{id}')
  // Profile > Orders (only Package Delivery (non FoodTech) orders) (path: '/profile/orders/{id}')
  .addEntry('order-item', './js/app/components/order/id/entrypoint.tsx')
  .addEntry('product-form', './js/app/product/form.js')
  .addEntry('package-set-form', './js/app/admin/package-set.js')
  .addEntry('product-list', './js/app/product/list.js')
  .addEntry('product-option-form', './js/app/forms/product-option.js')
  .addEntry('promotion-form', './js/app/forms/promotion.js')
  .addEntry('register', './js/app/register/index.js')
  .addEntry('restaurant-edenred', './js/app/restaurant/edenred.js')
  .addEntry('restaurant-form', './js/app/restaurant/form.js')
  .addEntry('restaurant-fulfillment-methods', './js/app/restaurant/fulfillment-methods.js')
  .addEntry('restaurant-list', './js/app/restaurant/list.js')
  .addEntry('restaurant-item', './js/app/restaurant/item.js')
  .addEntry('restaurant-menu-editor', './js/app/restaurant/menu-editor.js')
  .addEntry('restaurant-planning', './js/app/restaurant/planning.js')
  .addEntry('restaurant-preparation-time', './js/app/restaurant/preparationTime.js')
  .addEntry('restaurants-map', './js/app/restaurants-map/index.js')
  .addEntry('restaurant-dashboard', './js/app/dashboard/@restaurant/dashboard.js')
  .addEntry('search-address', './js/app/search/address.js')
  .addEntry('search-user', './js/app/search/user.js')
  .addEntry('sentry', './js/app/sentry.ts')
  .addEntry('store-form', './js/app/admin/store/form.js')
  .addEntry('stores-list', './js/app/admin/store/list.js')
  .addEntry('task-list', './js/app/delivery/task-list.js')
  .addEntry('time-slot-form', './js/app/time-slot/form.js')
  .addEntry('time-slot-list', './js/app/time-slot/list.js')
  .addEntry('user-tracking', './js/app/user/tracking.js')
  .addEntry('user-form', './js/app/user/form.js')
  .addEntry('user-invite', './js/app/user/invite.js')
  .addEntry('user-data', './js/app/user/user-data.js')
  .addEntry('widgets', './js/app/widgets/index.js')
  .addEntry('widgets-admin', './js/app/widgets/admin.js')
  .addEntry('zone-preview', './js/app/zone/preview.js')
  .addEntry('failure-form', './js/app/failure/form.js')
  .addEntry('incidents-ux-react-controllers', './js/app/admin/incidents/ux-react-controllers/index.js')
  .addEntry('incidents-list', './js/app/admin/incidents/incidents-list.tsx')
  .addEntry('incident-details', './js/app/admin/incidents/[id]/incident-details.tsx')

  // @see https://symfony.com/doc/current/frontend/encore/custom-loaders-plugins.html#adding-custom-plugins
  // @see https://github.com/moment/moment/issues/2373
  // @see https://github.com/jmblog/how-to-optimize-momentjs-with-webpack
  .addPlugin(new webpack.ContextReplacementPlugin(
    /moment[/\\]locale$/,
    /ca|de|es|fr|pl|pt-br/
  ))

  /**
   * added to fix:
   * "BREAKING CHANGE: The request failed to resolve only because it was resolved as fully specified
   * (probably because the origin is strict EcmaScript Module,
   * e. g. a module with javascript mimetype, a '.mjs' file, or a '.js' file
   * where the package.json contains '"type": "module"')."
   * in @cubejs-client
    */
  .addRule({
    test: /\.m?js$/,
    resolve: {
      fullySpecified: false,
    },
  })

  .enableStimulusBridge('./assets/controllers.json')

  // When enabled, Webpack "splits" your files into smaller pieces for greater optimization.
  .splitEntryChunks()

  // will require an extra script tag for runtime.js
  // but, you probably want this, unless you're building a single-page app
  .enableSingleRuntimeChunk()

  .enableTypeScriptLoader(function(tsConfig) {
    tsConfig.transpileOnly = true
  })
  .enableReactPreset()

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

  .addPlugin(new webpack.ProvidePlugin({
    process: 'process/browser'
  }))

if (!Encore.isProduction()) {
  Encore.addPlugin(new ESLintPlugin({
    configType: 'flat',
    eslintPath: 'eslint/use-at-your-own-risk'
  }))
}

// https://github.com/webpack/webpack-dev-server/blob/master/CHANGELOG.md#400-beta0-2020-11-27
Encore.configureDevServerOptions(options => {
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

  options.host = '0.0.0.0'
  options.port = 8080

  options.client = {
    overlay: false
  }
})

Encore.addPlugin(new AntdMomentWebpackPlugin())

let webpackConfig = Encore.getWebpackConfig()

webpackConfig.stats = 'minimal'

module.exports = webpackConfig
