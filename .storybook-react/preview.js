/** @type { import('@storybook/server').Preview } */

import "../assets/css/main.scss";

import "../js/app/restaurant/list.scss";

import numbro from 'numbro'

Number.prototype.formatMoney = function() {
  return numbro(this).format({
    ...numbro.languageData().formats.fullWithTwoDecimals,
    currencySymbol: '€'
  })
}

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
    docs: {
      story: {
        iframeHeight: 250,
      }
    }
  },
};

export default preview;
