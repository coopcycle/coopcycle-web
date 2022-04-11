import React from 'react'
import { render } from 'react-dom'
import moment from 'moment'
import Swiper, { Navigation } from 'swiper'

import { asText } from '../components/ShippingTimeRange'

import 'swiper/css';
import 'swiper/css/navigation'

import './list.scss'

const FulfillmentBadge = ({ range }) => {

  return (
    <span className="restaurant-item__time-range">
      <i className="fa fa-clock-o mr-2"></i>
      <span>{ asText(range, false, true) }</span>
    </span>
  )
}

document.querySelectorAll('[data-fulfillment]').forEach(el => {
  $.getJSON(el.dataset.fulfillment).then(data => {

    if (!data.delivery && !data.collection) {
      return
    }

    const ranges = []
    if (data.delivery && data.delivery.range) {
      ranges.push(data.delivery.range)
    }
    if (data.collection && data.collection.range) {
      ranges.push(data.collection.range)
    }

    ranges.sort((a, b) => moment(a[0]).isSame(b[0]) ? 0 : (moment(a[0]).isBefore(b[0]) ? -1 : 1))

    render(<FulfillmentBadge range={ ranges[0] } />, el)
  })
})

new Swiper('.swiper', {
  modules: [ Navigation ],
  slidesPerView: 1.25,
  spaceBetween: 2,
  slidesPerGroup: 1,
  navigation: {
    nextEl: '.swiper-nav-next',
    prevEl: '.swiper-nav-prev',
  },
  lazyLoading: true,
  breakpoints: {
    480: {
      slidesPerView: 2.25,
      spaceBetween: 2,
      slidesPerGroup: 1,
    },
    768: {
      slidesPerView: 3.25,
      spaceBetween: 2,
      slidesPerGroup: 2,
    },
    992: {
      slidesPerView: 4.25,
      spaceBetween: 3,
      slidesPerGroup: 2,
    },
    1200: {
      slidesPerView: 5.25,
      spaceBetween: 4,
      slidesPerGroup: 3,
    },
  },
  observer: true, // to be initialized properly inside a hidden container
  observeParents: true,
  on: {
    afterInit: function() {
      // we need to hide the swiper at the begining because until is not initialized the images do not look very well
      document.querySelectorAll('.homepage-restaurants').forEach(element => element.classList.remove('hidden'))
    }
  }
})

$('.shops-side-bar-filters input[type=radio]').on('click', function (e) {
  // avoid clicking another radio before navigate
  document.querySelectorAll(".shops-side-bar-filters input[type=radio]:not(input[type=radio]:checked)").forEach((radio) => radio.disabled=true)
  $(e.target).closest('form').submit()
});

$('.shops-side-bar-filters input[type=checkbox]').on('click', function (e) {
  // avoid clicking another radio before navigate
  document.querySelectorAll(".shops-side-bar-filters input[type=checkbox]:not(input[type=checkbox]:checked)").forEach((check) => check.disabled=true)
  $(e.target).closest('form').submit()
});
