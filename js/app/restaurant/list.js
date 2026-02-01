import Swiper from 'swiper'
import { Navigation } from 'swiper/modules'

import  _ from 'lodash'

import 'swiper/css';
import 'swiper/css/navigation'
import 'swiper/css/free-mode'

import './list.scss'

import i18n from '../i18n'

/**
 * Turn off automatic browser handle of scroll
 *
 * The browser by default saves the last position of the scroll so that when
 * the user returns to the screen (e.g. when going to a restaurant and back again)
 * returns to the last section of the list that has been viewing.
 * But in our case we don't load the whole list, but we load it as the user scrolls
 * therefore when returning to the screen the last position of the scroll in a previous navigation
 * may be beyond the amount of elements we are displaying.

 * By disabling the default scroll handling we make the browser not want to position the user in a place
 * that no longer exists because the list has been restarted.
 */
window.history.scrollRestoration = 'manual'

// Make sure that the same values are set in css
const CONTAINER_MARGIN = 16
const CONTAINER_MARGIN_MD = 64
const ITEM_MARGIN = 16
const ITEM_WIDTH = 333

const calculateBreakpoints = () => {
  const breakpoints = {}

  let listOfWidths = _.range(480, 1920, 50)
  listOfWidths.push(576, 768, 992, 1200, 1600, 1920) // add bootstrap breakpoints
  listOfWidths = _.uniq(listOfWidths)
  listOfWidths.sort((a, b) => a - b)

  listOfWidths.forEach(width => {
    const containerMargin = width >= 768 ? CONTAINER_MARGIN_MD : CONTAINER_MARGIN
    const slidesPerView = (width - containerMargin * 2) / (ITEM_WIDTH + ITEM_MARGIN * 2)

    breakpoints[width] = {
      slidesPerView,
      slidesPerGroup: Math.floor(slidesPerView),
    }
  })

  return breakpoints
}

const swiperBreakpoints = calculateBreakpoints()

new Swiper('.restaurant-collection-swiper', {
  modules: [ Navigation ],
  slidesPerView: 'auto',
  slidesPerGroup: 1,
  navigation: {
    nextEl: '.swiper-nav-next',
    prevEl: '.swiper-nav-prev',
  },
  lazyLoading: true,
  breakpoints: swiperBreakpoints,
  observer: true, // to be initialized properly inside a hidden container
  observeParents: true
})

new Swiper('.cuisines', {
  direction: 'horizontal',
  freeMode: true,
  slidesPerView: 'auto',
  lazyLoading: false,
  snapToSlideEdge: true,
  sticky: true,
  spaceBetween: 0
})

new Swiper('.categories', {
  direction: 'horizontal',
  freeMode: true,
  slidesPerView: 'auto',
  lazyLoading: false,
  snapToSlideEdge: true,
  sticky: true,
  spaceBetween: 0
})

/**
 * When the user clicks on a restaurant and
 * there is no address scroll into the search bar, and ask for an address.
 */
// eslint-disable-next-line no-unused-vars
function applyClickListenerForRestaurantItem() {
  document.querySelectorAll('[data-restaurant-path]').forEach(el => {
    el.addEventListener("click", function (e) {

      const searchParams = new URLSearchParams(window.location.search);

      // check if there is an address query param
      if (!searchParams.has('address')) {
        // if there is not an address do not navigate to restaurant page
        e.preventDefault()

        // scroll into the search bar and ask for an address
        document.querySelectorAll('[data-search="address"]').forEach((container) => {
          const el = container.querySelector('[data-element]')
          if (el) {
            el.scrollIntoView({behavior: "smooth"})
            const inputEl = el.querySelector('input[type="search"]')
            if (inputEl) {
              inputEl.focus();
            }
          }
        })
      }

    })
  })
}

// applyClickListenerForRestaurantItem()
