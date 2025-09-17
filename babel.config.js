module.exports = {
  "presets": [
    ["@babel/preset-env", {
      "useBuiltIns": "usage",
      "corejs": 3
    }],
    "@babel/preset-react",
    "@babel/preset-typescript"
  ],
  "plugins": [
    "@babel/plugin-proposal-object-rest-spread",
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
