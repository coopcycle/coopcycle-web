// Webpack configuration used mainly in Cypress Component Testing

const Encore = require('@symfony/webpack-encore')
require('./webpack.config.js')

Encore

  .setOutputPath(__dirname + '/web/build-cypress')
  .setPublicPath('/build-cypress')

// Fixes an issue on macOS: Error: EPERM: operation not permitted, mkdir '/Users/../Library/Caches/Cypress/../Cypress.app/Contents/Resources/app/packages/server/node_modules/@cypress/webpack-dev-server/dist/dist'
Encore.configureManifestPlugin(options => {
  options.writeToFileEmit = false
})

let webpackConfig = Encore.getWebpackConfig()

//FIXME: re-enable if still needed or remove
// module.exports = {
//   mode: 'development',
//   module: {
//     rules: [
//       webpackConfig.module.rules[0],
//       // Do *NOT* use the configuration returned by Encore for images,
//       // to avoid the error "Automatic publicPath is not supported in this browser"
//       // https://github.com/cypress-io/cypress/issues/8900
//       {
//         test: /\.(png|jpg|jpeg|gif|ico|svg|webp)$/i,
//         use: [
//           {
//             loader: 'url-loader',
//           },
//         ],
//       },
//       webpackConfig.module.rules[1],
//       webpackConfig.module.rules[4],
//     ],
//   },
//   devtool: false,
//   plugins: webpackConfig.plugins,
// }

// Babel configuration
for (const rule of webpackConfig.module.rules) {
  if (rule.test && (rule.test.toString().includes('js') || rule.test.toString().includes('ts')) ) {
    // https://babeljs.io/docs/en/babel-plugin-transform-modules-commonjs
    // loose ES6 modules allow us to dynamically mock imports during tests
    // from
    // https://github.com/cypress-io/cypress/tree/master/npm/react/cypress/component/advanced/mocking-imports
    // https://github.com/cypress-io/cypress/discussions/16741#discussioncomment-7212638
    if (rule.use) {
      if (!rule.use[0].options.plugins) {
        rule.use[0].options.plugins = []
      }

      rule.use[0].options.plugins.push(['@babel/plugin-transform-modules-commonjs', { loose: true }])
    }
  }
}

module.exports = webpackConfig
