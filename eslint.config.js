const tseslint = require('typescript-eslint')
const reactPlugin = require('eslint-plugin-react')
const reactCompiler = require('eslint-plugin-react-compiler')
const pluginCypress = require('eslint-plugin-cypress/flat')
const reactHooks = require('eslint-plugin-react-hooks')
const jest = require('eslint-plugin-jest')
const storybook = require('eslint-plugin-storybook')

module.exports = [
  {
    plugins: {
      cypress: pluginCypress,
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
    ...tseslint.configs.recommendedTypeChecked,
  ].map(conf => ({
    ...conf,
    files: ['**/*.ts', '**/*.tsx'],
    languageOptions: {
      ...conf.languageOptions,
      parserOptions: {
        projectService: true,
        tsconfigRootDir: __dirname,
      },
    },
    rules: {
      ...conf.rules,
      // To facilitate TypeScript usage, convert all error rules to warnings for now
      ...Object.fromEntries(
        Object.entries(conf.rules || {}).map(([rule, config]) => {
          if (Array.isArray(config)) {
            return config[0] === 'error'
              ? [rule, ['warn', ...config.slice(1)]]
              : [rule, config]
          }
          return config === 'error' ? [rule, 'warn'] : [rule, config]
        }),
      ),
    },
  })),
  {
    files: ['**/*.ts', '**/*.tsx'],
    rules: {
      // TypeScript-specific rules
      '@typescript-eslint/no-floating-promises': 'off',
      //TODO: Gradually enable more TypeScript-specific rules
      '@typescript-eslint/no-explicit-any': 'error',
      // '@typescript-eslint/await-thenable': 'error',
      // '@typescript-eslint/ban-ts-comment': 'error',
      // '@typescript-eslint/no-array-constructor': 'error',
      // '@typescript-eslint/no-array-delete': 'error',
      // '@typescript-eslint/no-base-to-string': 'error',
      // '@typescript-eslint/no-duplicate-enum-values': 'error',
      // '@typescript-eslint/no-duplicate-type-constituents': 'error',
      // '@typescript-eslint/no-empty-object-type': 'error',
      // '@typescript-eslint/no-extra-non-null-assertion': 'error',
      // '@typescript-eslint/no-floating-promises': 'error',
      // '@typescript-eslint/no-for-in-array': 'error',
      // '@typescript-eslint/no-implied-eval': 'error',
      // '@typescript-eslint/no-misused-new': 'error',
      // '@typescript-eslint/no-misused-promises': 'error',
      // '@typescript-eslint/no-namespace': 'error',
      // '@typescript-eslint/no-non-null-asserted-optional-chain': 'error',
      // '@typescript-eslint/no-redundant-type-constituents': 'error',
      // '@typescript-eslint/no-require-imports': 'error',
      // '@typescript-eslint/no-this-alias': 'error',
      // '@typescript-eslint/no-unnecessary-type-assertion': 'error',
      // '@typescript-eslint/no-unnecessary-type-constraint': 'error',
      // '@typescript-eslint/no-unsafe-argument': 'error',
      // '@typescript-eslint/no-unsafe-assignment': 'error',
      // '@typescript-eslint/no-unsafe-call': 'error',
      // '@typescript-eslint/no-unsafe-declaration-merging': 'error',
      // '@typescript-eslint/no-unsafe-enum-comparison': 'error',
      // '@typescript-eslint/no-unsafe-function-type': 'error',
      // '@typescript-eslint/no-unsafe-member-access': 'error',
      // '@typescript-eslint/no-unsafe-return': 'error',
      // '@typescript-eslint/no-unsafe-unary-minus': 'error',
      // '@typescript-eslint/no-unused-expressions': 'error',
      // '@typescript-eslint/no-unused-vars': 'error',
      // '@typescript-eslint/no-wrapper-object-types': 'error',
      // '@typescript-eslint/only-throw-error': 'error',
      // '@typescript-eslint/prefer-as-const': 'error',
      // '@typescript-eslint/prefer-namespace-keyword': 'error',
      // '@typescript-eslint/prefer-promise-reject-errors': 'error',
      // '@typescript-eslint/require-await': 'error',
      // '@typescript-eslint/restrict-plus-operands': 'error',
      // '@typescript-eslint/restrict-template-expressions': 'error',
      // '@typescript-eslint/triple-slash-reference': 'error',
      // '@typescript-eslint/unbound-method': 'error',
    },
  },
]
