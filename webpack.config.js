var webpack = require("webpack");
var ExtractTextPlugin = require("extract-text-webpack-plugin");

module.exports = {
  entry: {
    cart: './js/app/cart/index.jsx',
    tracking: './js/app/tracking/index.js',
    homepage: './js/app/homepage/index.js',
    'order-payment': './js/app/order/payment.js',
    'order-tracking': './js/app/order/tracking.js',
    'profile-deliveries': './js/app/profile/deliveries.js',
  },
  output: {
    path: __dirname + '/web/js',
    filename: "[name].js",
  },
  module: {
    loaders: [
      // Extract css files
      {
          test: /\.css$/,
          loader: ExtractTextPlugin.extract("style-loader", "css-loader")
      },
      // Optionally extract less files
      // or any other compile-to-css language
      {
          test: /\.less$/,
          loader: ExtractTextPlugin.extract("style-loader", "css-loader!less-loader")
      },
      {
        test: /\.json$/,
        loader: "json-loader"
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
      new ExtractTextPlugin("[name].css"),
  ]
};