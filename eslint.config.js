const reactPlugin = require('eslint-plugin-react');
const reactCompiler = require('eslint-plugin-react-compiler');
const pluginCypress = require('eslint-plugin-cypress/flat');

module.exports = [
  // root: true,
  // env: {
  //   browser: true,
  //   es6: true,
  //   node: true,
  // },
  // extends: [
  //   'eslint:recommended',
  //   'plugin:react/recommended',
  //   'plugin:react-hooks/recommended',
  //   'plugin:jest/recommended',
  //   'plugin:storybook/recommended',
  //   'plugin:cypress/recommended',
  // ],
  // parser: 'babel-eslint',
  // parserOptions: {
  //   ecmaFeatures: {
  //     jsx: true,
  //   },
  //   ecmaVersion: 6,
  //   sourceType: 'module',
  // },
  {
    plugins: {
      // react: react,
      cypress: pluginCypress
      // 'eslint-plugin-react-compiler',
      // 'jest'
    },
  },
  // globals: {
  //   $: true,
  //   io: true,
  //   CoopCycle: true,
  //   Stripe: true,
  //   google: true,
  // },
  {
    settings: {
      react: {
        version: 'detect',
      },
    },
  },
  reactPlugin.configs.flat.recommended, // This is not a plugin object, but a shareable config object
  reactPlugin.configs.flat['jsx-runtime'], // Add this if you are using React 17+
  reactCompiler.configs.recommended,
  {
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
    }
  },
]
