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
    Mercadopago: true
  },
  settings: {
    react: {
      version: 'detect',
    },
  },
  rules: {
    'no-console': 'warn',
    'no-case-declarations': 'off',
    'no-extra-boolean-cast': 'off',
    'react/prop-types': 'off',
    'react/display-name': 'off',
  }
};
