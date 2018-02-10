var webpack = require("webpack");
var ExtractTextPlugin = require("extract-text-webpack-plugin");
var WebpackAssetsManifest = require('webpack-assets-manifest');
var CopyWebpackPlugin = require('copy-webpack-plugin');
var CommonChunkPlugin = require('webpack/lib/optimize/CommonsChunkPlugin');

if (process.env.NODE_ENV === 'production') {
  var jsFilename = "[name].[chunkhash].js",
      cssFilename = "[name].[contenthash].css";

}
else {
  var jsFilename = "[name].js",
      cssFilename = "[name].css";
}

var webpackConfig = {
  entry: {
    'css/dashboard': './assets/css/dashboard.scss',
    'css/styles': './assets/css/main.scss',
    'css/tracking': './assets/css/tracking.scss',
    'js/common': ['es6-set', 'whatwg-fetch', 'bootstrap'],
    'js/dashboard': './js/app/dashboard/index.jsx',
    'js/homepage': './js/app/homepage/index.js',
    'js/restaurant-list': './js/app/restaurant-list/index.jsx',
    'js/cart': './js/app/cart/index.jsx',
    'js/order': './js/app/order/index.jsx',
    'js/order-payment': './js/app/order/payment.js',
    'js/order-tracking': './js/app/order/tracking.jsx',
    'js/delivery-form': './js/app/delivery/form.jsx',
    'js/delivery-list': './js/app/delivery/list.jsx',
    'js/delivery-pricing-rules': './js/app/delivery/pricing-rules.jsx',
    'js/restaurant-form': './js/app/restaurant/form.jsx',
    'js/restaurant-menu': './js/app/restaurant/menu.jsx',
    'js/restaurant-planning': './js/app/restaurant/planning.jsx',
    'js/restaurant-panel': './js/app/restaurant/panel.jsx',
    'js/restaurants-map': './js/app/restaurants-map/index.jsx',
    'js/task-modal': './js/app/task/modal.js',
    'js/tracking': './js/app/tracking/index.jsx',
    'js/user-tracking': './js/app/user/tracking.jsx',
    'js/widgets/rule-picker': './js/app/widgets/RulePicker.js',
    'js/widgets/date-picker': './js/app/widgets/DatePicker.js',
    'js/widgets/opening-hours-parser': './js/app/widgets/OpeningHoursParser.jsx',
    'js/widgets/opening-hours-input': './js/app/widgets/OpeningHoursInput.jsx',
    'js/widgets/address-input': './js/app/widgets/AddressInput.jsx',
    'js/widgets/search': './js/app/widgets/Search.jsx',
    'js/widgets/timeline': './js/app/widgets/Timeline.js',
    'js/zone-preview': './js/app/zone/preview.jsx',
  },
  devtool: 'source-map',
  output: {
    publicPath: "/",
    path: __dirname + '/web',
    filename: jsFilename,
    sourceMapFilename: jsFilename + '.map'
  },
  resolve: {
    alias: {
      jquery: "jquery/src/jquery"
    }
  },
  module: {
    loaders: [
      {
          test: /\.scss$/,
          loader: ExtractTextPlugin.extract({
            fallback: 'style-loader',
            use: ['css-loader', 'sass-loader']
          })
      },
      {
          test: /\.css$/,
          loader: ExtractTextPlugin.extract({ fallback: 'style-loader', use: 'css-loader' })
      },
      {
          test: /\.(eot|ttf|woff|woff2)$/,
          loader: 'file-loader?name=css/fonts/[name].[ext]'
      },
      {
          test: /\.(svg|png)$/,
          loader: 'file-loader?name=css/images/[name].[ext]'
      },
      {
        test: /\.jsx?/,
        include: __dirname + '/js',
        loader: "babel-loader"
      }
    ]
  },
  // Use the plugin to specify the resulting filename (and add needed behavior to the compiler)
  plugins: [
      new ExtractTextPlugin({filename: cssFilename, allChunks: true}),
      new webpack.ProvidePlugin({
        $: "jquery",
        jQuery: "jquery",
      }),
      new CopyWebpackPlugin([
        { from: 'node_modules/coopcycle-js/build/coopcycle.js', to: 'js/coopcycle.js' }
      ]),
      new CommonChunkPlugin({
        name: 'js/common',
        minChunks: 3,
        filename: jsFilename
      })
  ],
  devServer: {
      headers: { "Access-Control-Allow-Origin": "*" },
      contentBase: __dirname + '/web',
      stats: 'minimal',
      compress: true,
      watchOptions: {
          ignored: /node_modules/,
          poll: 1000
      }
  }
};

if (process.env.NODE_ENV === 'production') {
  webpackConfig.plugins.push(new WebpackAssetsManifest({
    writeToDisk: true
  }));
}

module.exports = webpackConfig;
