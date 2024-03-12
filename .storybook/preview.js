/** @type { import('@storybook/server').Preview } */

import "../assets/css/main.scss";
import "../js/app/restaurant/list.scss";
import "../js/app/restaurant/menu.scss";

const preview = {
  parameters: {
    server: {
      url: `http://localhost/storybook/component`,
    },
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
    docs: {
      story: {
        iframeHeight: 250,
      }
    }
  },
};

export default preview;
