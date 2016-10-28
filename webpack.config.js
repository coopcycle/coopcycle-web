module.exports = {
  entry: [
    './js/index.jsx'
  ],
  output: {
    path: __dirname + '/web/js',
    filename: "app.js"
  },
  module: {
    loaders: [
      {
        test: /\.jsx?/,
        include: __dirname + '/js',
        loader: "babel-loader"
      }
    ]
  },
};