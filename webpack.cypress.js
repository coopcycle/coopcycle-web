var Encore = require('@symfony/webpack-encore')
require("./webpack.config.js")

Encore

  .setOutputPath(__dirname + '/web/build-cypress')
  .setPublicPath('/build-cypress')

// Fixes an issue on macOS: Error: EPERM: operation not permitted, mkdir '/Users/../Library/Caches/Cypress/../Cypress.app/Contents/Resources/app/packages/server/node_modules/@cypress/webpack-dev-server/dist/dist'
Encore.configureManifestPlugin((options) => {
  options.writeToFileEmit = false
})

let webpackConfig = Encore.getWebpackConfig()

module.exports = webpackConfig

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
