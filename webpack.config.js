var path = require("path");
var webpack = require("webpack");
var ExtractTextPlugin = require("extract-text-webpack-plugin");
var WebpackAssetsManifest = require('webpack-assets-manifest');
var CopyWebpackPlugin = require('copy-webpack-plugin');

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
    'css/styles': './assets/css/main.scss',
    'css/tracking': './assets/css/tracking.scss',
    'js/address-form': './js/app/address/form.jsx',
    'js/bootstrap': 'bootstrap',
    'js/cart': './js/app/cart/index.jsx',
    'js/delivery-form': './js/app/delivery/form.jsx',
    'js/homepage': './js/app/homepage/index.js',
    'js/order-address': './js/app/order/address.jsx',
    'js/order-payment': './js/app/order/payment.js',
    'js/order-tracking': [ 'whatwg-fetch', './js/app/order/tracking.jsx' ],
    'js/profile-deliveries': './js/app/profile/deliveries.js',
    'js/restaurant-form': './js/app/restaurant/form.jsx',
    'js/tracking': './js/app/tracking/index.jsx',
  },
  output: {
    publicPath: "/",
    path: __dirname + '/web',
    filename: jsFilename
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
  ],
  devServer: {
      headers: { "Access-Control-Allow-Origin": "*" },
      contentBase: __dirname + '/web',
      stats: 'minimal',
      compress: true,
  }
};

if (process.env.NODE_ENV === 'production') {
  webpackConfig.plugins.push(new WebpackAssetsManifest({
    writeToDisk: true
  }));
}

module.exports = webpackConfig;
