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
