var path = require("path");
var webpack = require("webpack");
var ExtractTextPlugin = require("extract-text-webpack-plugin");
var WebpackAssetsManifest = require('webpack-assets-manifest');

if (process.env.NODE_ENV === 'production') {
  var jsFilename = "js/[name].[chunkhash].js",
      cssFilename = "css/[name].[contenthash].css";

}
else {
  var jsFilename = "js/[name].js",
      cssFilename = "css/[name].css";
}

var webpackConfig = {
  entry: {
    'bootstrap': 'bootstrap',
    'cart': './js/app/cart/index.jsx',
    'homepage': './js/app/homepage/index.js',
    'order-payment': './js/app/order/payment.js',
    'order-tracking': [ 'whatwg-fetch', './js/app/order/tracking.jsx' ],
    'profile-deliveries': './js/app/profile/deliveries.js',
    'restaurant-form': './js/app/restaurant/form.jsx',
    'delivery-form': './js/app/delivery/form.jsx',
    'styles': './assets/css/main.scss',
    'tracking': [ './assets/css/tracking.scss', './js/app/tracking/index.jsx' ]
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
          loader: ExtractTextPlugin.extract({ fallback: 'style-loader', use: 'css-loader!sass-loader' })
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
        jQuery: "jquery"
      })
  ],
  devServer: {
      headers: { "Access-Control-Allow-Origin": "*" },
      contentBase: __dirname + '/web',
      stats: 'minimal',
      compress: true,
      public: '192.168.99.100:8080'
  }
};

if (process.env.NODE_ENV === 'production') {
  var manifest = new WebpackAssetsManifest({writeToDisk: true});
  manifest.getAssetKey = function (name, filename) {
    return path.join(path.dirname(filename), name) + this.getExtension(filename);
  };
  webpackConfig.plugins.push(manifest);
}

module.exports = webpackConfig;
