import React, { useState, useEffect, createRef } from 'react'
import { Carousel } from 'antd'
import classNames from 'classnames'

import '../../../../assets/css/dotstyle.css'
import './dotstyle.scss'

const carouselRef = createRef()

export default ({ images }) => {

  const [ slide, setSlide ] = useState(0)

  useEffect(() => {
    carouselRef.current.goTo(slide)
  }, [ slide ])

  return (
    <div>
      <div className="mb-4">
        <Carousel ref={ carouselRef } dots={ false }>
          { images.map((image, index) => (
            <div key={ `image-${index}` }>
              <img src={ image } className="img-responsive" />
            </div>
          )) }
        </Carousel>
      </div>
      <div className="dotstyle dotstyle-scaleup text-center">
        <ul>
          { images.map((image, index) => (
            <li key={ `dot-${index}` } className={ classNames({ current: index === slide }) }>
              <a href="#" onClick={ e => {
                e.preventDefault()
                setSlide(index)
              }}>{ image }</a>
            </li>
          ))}
        </ul>
      </div>
    </div>
  )
}
