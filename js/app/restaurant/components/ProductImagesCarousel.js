import React from 'react'
import { Carousel } from 'antd'

export default ({ images }) => (
  <Carousel>
    { images.map((image, index) => (
      <div key={ `image-${index}` } style={{ textAlign: 'center' }}>
        <img src={ image } className="img-responsive" />
      </div>
    )) }
  </Carousel>
)
