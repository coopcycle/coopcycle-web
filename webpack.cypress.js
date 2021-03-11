var Encore = require('@symfony/webpack-encore')
var webpack = require('webpack')

// Manually configure the runtime environment if not already configured yet by the "encore" command.
// It's useful when you use tools that rely on webpack.config.js file.
if (!Encore.isRuntimeEnvironmentConfigured()) {
  Encore.configureRuntimeEnvironment('dev');
}

Encore

  .setOutputPath(__dirname + '/web/build-cypress')
  .setPublicPath('/build-cypress')

  .addEntry('common', './js/app/common.js')

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

  .disableCssExtraction()

let webpackConfig = Encore.getWebpackConfig()

module.exports = {
  mode: 'development',
  module: {
    rules: [
      webpackConfig.module.rules[0],
      // Do *NOT* use the configuration returned by Encore for images,
      // to avoid the error "Automatic publicPath is not supported in this browser"
      // https://github.com/cypress-io/cypress/issues/8900
      {
        test: /\.(png|jpg|jpeg|gif|ico|svg|webp)$/i,
        use: [
          {
            loader: 'url-loader',
          },
        ],
      },
      webpackConfig.module.rules[1],
      webpackConfig.module.rules[4],
    ],
  },
  devtool: false,
  plugins: webpackConfig.plugins,
}
