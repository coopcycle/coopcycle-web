module.exports = {
  env: {
    "COMMAND_PREFIX": "docker-compose exec -T php",
    "coverage": false,
  },
  chromeWebSecurity: false,
  e2e: {
    baseUrl: 'http://localhost:9080'
  },
  component: {
    devServer: {
      framework: 'react',
      bundler: 'webpack',
      // optionally pass in webpack config
      webpackConfig: require('./webpack.cypress'),
    },
  },
}
