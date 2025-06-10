module.exports = {
  "presets": [
    ["@babel/preset-env", {
      "useBuiltIns": "usage",
      "corejs": 3
    }],
    "@babel/preset-react"
  ],
  "plugins": [
    "@babel/plugin-proposal-object-rest-spread",
    "@babel/plugin-proposal-optional-chaining",
    // https://babeljs.io/docs/en/babel-plugin-transform-modules-commonjs
    // loose ES6 modules allow us to dynamically mock imports during tests
    // from https://github.com/cypress-io/cypress/tree/master/npm/react/cypress/component/advanced/mocking-imports
    [
      '@babel/plugin-transform-modules-commonjs',
      {
        loose: true,
      },
    ],
    ["import", { "libraryName": "antd", "style": (path, file) => {
      if (path === 'antd/lib/col' || path === 'antd/lib/row') {
        return 'antd/lib/grid/style/index.css'
      }

      return `${path}/style/index.css`
    } }]
  ],
  "env": {
    "test": {
      "presets": [
        ["@babel/preset-env", {
          "targets": {
            "node": "current",
          }
        }]
      ]
    }
  }
}
