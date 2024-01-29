/** @type { import('@storybook/server').Preview } */

import "../assets/css/main.scss";
import "../js/app/restaurant/list.scss";

const preview = {
  parameters: {
    actions: { argTypesRegex: "^on[A-Z].*" },
    controls: {
      matchers: {
        color: /(background|color)$/i,
        date: /Date$/i,
      },
    },
    options: {
      storySort: {
        method: "alphabetical",
        locales: "en-US",
      },
    },
  },
};

export default preview;
