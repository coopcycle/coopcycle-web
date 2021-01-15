// @see https://webpack.js.org/guides/author-libraries/

const path = require('path');

module.exports = {
  entry: './js/sdk/index.js',
  output: {
    path: path.resolve(__dirname, 'web/js/'),
    filename: 'coopcycle.js',
    library: 'CoopCycle',
  },
  module: {
    rules: [
      {
        test: /\.html$/i,
        loader: 'html-loader',
      },
      {
        test: /\.css$/i,
        use: ['to-string-loader', 'css-loader'],
      },
    ],
  },
};
