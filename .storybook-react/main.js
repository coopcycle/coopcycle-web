/** @type { import('@storybook/react-webpack5').StorybookConfig } */

import MiniCssExtractPlugin from 'mini-css-extract-plugin'
import custom from '../webpack.config.js'

const config = {
  stories: [
    "../js/app/**/*.stories.@(js|jsx|mjs|ts|tsx)",
  ],
  addons: ["@storybook/addon-links", "@storybook/addon-essentials"],
  framework: {
    name: "@storybook/react-webpack5",
    options: {},
  },
  docs: {
    autodocs: "tag",
  },
  core: {
    disableTelemetry: true,
  },
  webpackFinal: async (config) => {
    config.plugins.push(new MiniCssExtractPlugin())

    config.output.path = config.output.path.replace('/storybook/public', '/storybook-react/public')

    return {
      ...config,
      module: {
        ...config.module,
        rules: [
          ...config.module.rules,
          ...custom.module.rules
        ]
      },
    };
  },
};
export default config;
