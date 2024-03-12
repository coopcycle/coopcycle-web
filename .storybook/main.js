/** @type { import('@storybook/server-webpack5').StorybookConfig } */

import MiniCssExtractPlugin from 'mini-css-extract-plugin'
import custom from '../webpack.config.js'

const config = {
  refs: {
    react: {
      title: 'React',
      url: 'http://localhost:6007',
    },
  },
  stories: [
    "../src/**/*.mdx",
    "../src/**/*.stories.@(json|yaml|yml)",
    "../templates/**/*.stories.json"
  ],
  addons: ["@storybook/addon-links", "@storybook/addon-essentials"],
  framework: {
    name: "@storybook/server-webpack5",
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
