import React from 'react'
import SwiperCore, { Pagination } from 'swiper'
import { Swiper, SwiperSlide } from 'swiper/react'

import 'swiper/swiper.scss'
import 'swiper/components/pagination/pagination.scss';

SwiperCore.use([ Pagination ])

export default ({ images }) => (
  <Swiper
    slidesPerView={ 2 }
    centeredSlides={ true }
    pagination={{ clickable: true }}
  >
    { images.map((image, index) => (
      <SwiperSlide key={ `image-${index}` }>
        <img src={ image } />
      </SwiperSlide>
    )) }
  </Swiper>
)
