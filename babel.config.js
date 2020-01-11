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
    ["import", { "libraryName": "antd", "style": (path, file) => `${path}/style/index.css` }]
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
