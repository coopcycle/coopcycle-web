module.exports = {
  root: true,
  env: {
    browser: true,
    es6: true,
    node: true,
  },
  extends: [
    'eslint:recommended',
    'plugin:react/recommended',
    'plugin:jest/recommended',
  ],
  parser: 'babel-eslint',
  parserOptions: {
    ecmaFeatures: {
      jsx: true
    },
    ecmaVersion: 6,
    sourceType: 'module',
  },
  plugins: [
    'react',
    'jest',
  ],
  globals: {
    '$': true,
    io: true,
    CoopCycle: true,
    Stripe: true,
    google: true,
  },
  settings: {
    react: {
      version: 'detect',
    },
  },
  rules: {
    'no-case-declarations': 0,
    'no-extra-boolean-cast': 0,
    'react/prop-types': 0,
    'react/display-name': 0,
  }
};
