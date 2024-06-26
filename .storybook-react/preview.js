/** @type { import('@storybook/server').Preview } */

import '../assets/css/main.scss'

import '../js/app/restaurant/list.scss'
import '../js/app/restaurant/menu.scss'
import '../js/app/restaurant/item.scss'
import '../js/app/restaurant/components/ProductDetails/productDetails.scss'
import '../js/app/restaurant/components/ProductDetails/dotstyle.scss'
import '../js/app/restaurant/components/Order/index.scss'
import '../js/app/components/order/index.scss'

import numbro from 'numbro'

import jquery from 'jquery'
global.$ = jquery

global.Routing = {
  generate: (route, params) => {
    return route
  },
}

Number.prototype.formatMoney = function () {
  return numbro(this).format({
    ...numbro.languageData().formats.fullWithTwoDecimals,
    currencySymbol: '€',
  })
}

const preview = {
  parameters: {
    actions: { argTypesRegex: '^on[A-Z].*' },
    controls: {
      matchers: {
        color: /(background|color)$/i,
        date: /Date$/i,
      },
    },
    options: {
      storySort: {
        method: 'alphabetical',
        locales: 'en-US',
      },
    },
    docs: {
      story: {
        iframeHeight: 250,
      },
    },
  },
}

export default preview
