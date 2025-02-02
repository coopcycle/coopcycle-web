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
    'plugin:react-hooks/recommended',
    'plugin:jest/recommended',
    'plugin:storybook/recommended',
    'plugin:cypress/recommended',
  ],
  parser: 'babel-eslint',
  parserOptions: {
    ecmaFeatures: {
      jsx: true,
    },
    ecmaVersion: 6,
    sourceType: 'module',
  },
  plugins: [
    'react',
    'eslint-plugin-react-compiler',
    'jest'
  ],
  globals: {
    $: true,
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
    'no-console': 'warn',
    'no-case-declarations': 'off',
    'no-extra-boolean-cast': 'off',
    'react/prop-types': 'off',
    'react/display-name': 'off',
    'react/no-deprecated': 'warn',
    'react-compiler/react-compiler': 'warn',
    'cypress/unsafe-to-chain-command': 'warn',
    'cypress/no-unnecessary-waiting': 'warn',
  },
}
