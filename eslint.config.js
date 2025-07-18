const reactPlugin = require('eslint-plugin-react');
const reactCompiler = require('eslint-plugin-react-compiler');
const pluginCypress = require('eslint-plugin-cypress/flat');
const reactHooks = require('eslint-plugin-react-hooks');
const jest = require('eslint-plugin-jest');
const storybook = require('eslint-plugin-storybook');
const typescriptEslint = require('@typescript-eslint/eslint-plugin');
const typescriptParser = require('@typescript-eslint/parser');

module.exports = [
  {
    plugins: {
      cypress: pluginCypress
    },
  },
  {
    settings: {
      react: {
        version: 'detect',
      },
    },
  },
  // FIXME Doesn't work
  // pluginCypress.configs.recommended,
  jest.configs['flat/recommended'],
  ...storybook.configs['flat/recommended'],
  reactPlugin.configs.flat.recommended, // This is not a plugin object, but a shareable config object
  reactPlugin.configs.flat['jsx-runtime'], // Add this if you are using React 17+
  reactHooks.configs['recommended-latest'],
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
    },
  },
  // TypeScript configuration
  ...[
    // eslint.configs['flat/recommended'],
    ...typescriptEslint.configs['flat/recommended'],
  ].map(conf => ({
    ...conf,
    files: ['**/*.ts', '**/*.tsx'],
  })),
  {
    files: ['**/*.ts', '**/*.tsx'],
    languageOptions: {
      parser: typescriptParser,
      parserOptions: {
        ecmaVersion: 'latest',
        sourceType: 'module',
        ecmaFeatures: {
          jsx: true,
        },
        project: './tsconfig.json',
      },
    },
    plugins: {
      '@typescript-eslint': typescriptEslint,
    },
    rules: {
      // TypeScript-specific rules
      '@typescript-eslint/no-unused-vars': [
        'warn',
        { argsIgnorePattern: '^_' },
      ],
      '@typescript-eslint/no-explicit-any': 'warn',
      '@typescript-eslint/ban-ts-comment': 'warn',

      '@typescript-eslint/no-unsafe-assignment': 'warn',
      '@typescript-eslint/no-unsafe-call': 'warn',
      '@typescript-eslint/no-unsafe-member-access': 'warn',
      '@typescript-eslint/no-unsafe-return': 'warn',
      '@typescript-eslint/no-unsafe-argument': 'warn',
      '@typescript-eslint/no-floating-promises': 'warn',
      '@typescript-eslint/await-thenable': 'warn',
      '@typescript-eslint/no-misused-promises': 'warn',
      '@typescript-eslint/require-await': 'warn',
      '@typescript-eslint/prefer-optional-chain': 'warn',
      '@typescript-eslint/no-non-null-assertion': 'warn',
      '@typescript-eslint/no-base-to-string': 'warn',
      '@typescript-eslint/restrict-plus-operands': 'warn',
      '@typescript-eslint/restrict-template-expressions': 'warn',

      // Disable conflicting ESLint rules for TypeScript files
      'no-unused-vars': 'off',
      'require-await': 'off', // Use @typescript-eslint version
    },
  },
]
