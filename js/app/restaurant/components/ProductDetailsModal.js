import React from 'react'
import SwiperCore, { Pagination } from 'swiper'
import { Swiper, SwiperSlide } from 'swiper/react'

import 'swiper/swiper.scss'
import 'swiper/components/pagination/pagination.scss';

SwiperCore.use([ Pagination ])

export default ({ images }) => (
  <Swiper
    slidesPerView={ 1 }
    centeredSlides={ true }
    pagination={{ clickable: true }}
  >
    { images.map((image, index) => (
      <SwiperSlide key={ `image-${index}` } style={{ textAlign: 'center' }}>
        <img src={ image } className="img-responsive" />
      </SwiperSlide>
    )) }
  </Swiper>
)
