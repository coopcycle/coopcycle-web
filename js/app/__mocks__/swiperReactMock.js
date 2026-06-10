const React = require('react')

function Swiper({ children }) {
  return React.createElement('div', { 'data-testid': 'swiper' }, children)
}

function SwiperSlide({ children }) {
  return React.createElement('div', { 'data-testid': 'swiper-slide' }, children)
}

module.exports = { Swiper, SwiperSlide }
