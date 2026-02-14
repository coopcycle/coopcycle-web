import React, { useEffect, useState, createRef } from 'react'
import { createRoot } from 'react-dom/client'
import { Swiper, SwiperSlide, useSwiper } from 'swiper/react';
import {
  CloseOutlined,
  EditOutlined,
  PlusSquareOutlined,
} from '@ant-design/icons';
import { Button, Flex, Tooltip } from 'antd';
import sanitizeHtml from 'sanitize-html';

const LastSlideContent = ({ onClick }) => {

  const swiper = useSwiper()

  return (
    <a href="#" onClick={(e) => {
      e.preventDefault()
      onClick()
      setTimeout(() => swiper.slideTo(swiper.slides.length, 500), 50)
    }}>
      <PlusSquareOutlined />
    </a>
  )
}

const SliderEditor = ({ defaultSlides, onChange }) => {

  const [slides, setSlides] = useState(defaultSlides)

  useEffect(() => {
    onChange(slides)
  }, [slides, onChange])

  return (
    <Swiper
      wrapperTag="ol"
      className="swiper-homepage"
      spaceBetween={20}
      slidesPerView={3}
      onSlideChange={() => console.log('slide change')}
      onSwiper={(swiper) => console.log(swiper)}
    >
      { slides.map((slide, index) => {
        return (
          <SwiperSlide
            tag="li"
            key={`slide-${index}`}>
            <span className="swiper-slide-toolbar">
              <Flex>
                <Tooltip title="Edit">
                  <Button type="text" shape="circle" icon={<EditOutlined />} />
                </Tooltip>
                <Tooltip title="Delete">
                  <Button type="text" shape="circle" icon={<CloseOutlined />} onClick={() => {
                    const newSlides = [...slides]
                    newSlides.splice(index, 1)
                    setSlides(newSlides)
                  }} />
                </Tooltip>
              </Flex>
            </span>
            <a href="#">
              <div className="swiper-slide-content">
                <div className="swiper-slide-content-left">
                  <h4
                    contentEditable={true}
                    suppressContentEditableWarning={true}
                    onInput={(e) => {
                      const newSlides = [...slides]
                      newSlides.splice(index, 1, {
                        ...slides[index],
                        title: sanitizeHtml(e.target.innerText)
                      })
                      setSlides(newSlides)
                    }}>{slide.title}</h4>
                  <p
                    contentEditable={true}
                    suppressContentEditableWarning={true}
                    onInput={(e) => {
                      const newSlides = [...slides]
                      newSlides.splice(index, 1, {
                        ...slides[index],
                        text: sanitizeHtml(e.target.innerText)
                      })
                      setSlides(newSlides)
                    }}>{slide.text}</p>
                  <button type="button" className="btn btn-xs">
                    <span
                      contentEditable={true}
                      suppressContentEditableWarning={true}
                      onInput={(e) => console.log('Edited', e.target.innerText)}>{slide.buttonText}</span>
                  </button>
                </div>
                <div className="swiper-slide-content-right"></div>
              </div>
            </a>
          </SwiperSlide>
        )
      }) }
      <SwiperSlide tag="li">
        <LastSlideContent onClick={() => {
          setSlides([
            ...slides,
            {
              href: '#',
              title: 'New title',
              text: 'New slide',
              buttonText: 'Learn more'
            }
          ])
        }} />
      </SwiperSlide>
    </Swiper>
  );
}

export default class Slider {

  static get toolbox() {
    return {
      title: 'Slider',
      // https://lucide.dev/icons/arrow-left-right
      icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-arrow-left-right-icon lucide-arrow-left-right"><path d="M8 3 4 7l4 4"/><path d="M4 7h16"/><path d="m16 21 4-4-4-4"/><path d="M20 17H4"/></svg>'
    };
  }

  constructor({ data, config }) {
    this.config = config;
    this.slides = data.slides ?? [
      {
        href: '#',
        title: 'Title 1',
        text: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit',
        buttonText: 'Learn more',
      },
      {
        href: '#',
        title: 'Title 2',
        text: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit',
        buttonText: 'Learn more',
      },
    ]
  }

  render() {

    const wrapper = document.createElement('div');
    wrapper.style.marginBottom = '20px'

    const sliderEditorEl = document.createElement('div');

    createRoot(sliderEditorEl).render(<SliderEditor
      defaultSlides={this.slides}
      onChange={(slides) => {
        console.log('Slides updated', slides)
      }} />)

    wrapper.appendChild(sliderEditorEl)

    return wrapper;
  }

  save(blockContent) {
    return {}
  }
}
