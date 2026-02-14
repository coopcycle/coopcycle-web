import React, { useEffect, useState, createRef, useRef } from 'react'
import { createRoot } from 'react-dom/client'
import { Swiper, SwiperSlide, useSwiper } from 'swiper/react';
import {
  CloseOutlined,
  EditOutlined,
  PlusSquareOutlined,
  LinkOutlined,
  UploadOutlined,
} from '@ant-design/icons';
import { ColorPicker, Button, Flex, Tooltip, Popover, Input } from 'antd';
import sanitizeHtml from 'sanitize-html';
import ContentEditable from 'react-contenteditable'
import Uppy from '@uppy/core'
import Dashboard from '@uppy/dashboard';
import XHR from '@uppy/xhr-upload';

const LastSlideContent = ({ onClick }) => {

  const swiper = useSwiper()

  return (
    <a href="#" onClick={(e) => {
      e.preventDefault()
      onClick()
      setTimeout(() => swiper.slideTo(swiper.slides.length, 500), 50)
    }}>
      <PlusSquareOutlined style={{ fontSize: '24px' }} />
    </a>
  )
}

const SlideContent = ({ uploadEndpoint, slide, index, onChange }) => {

  const title = useRef(slide.title);
  const text = useRef(slide.text);
  const buttonText = useRef(slide.buttonText);

  const uniqueKey = `swiper-image-upload-button-${index}`

  useEffect(() => {
    console.log(`Configuring Uppy with ${uploadEndpoint} for slide ${index}`)
    const uppy = new Uppy({
      id: uniqueKey
    })
      .use(Dashboard, {
        trigger: `#${uniqueKey}`,
        inline: false,
        target: 'body'
      })
      .use(XHR, {
        endpoint: uploadEndpoint,
        // Only send our own metadata fields.
        allowedMetaFields: ['type', 'slide'],
      });
    uppy.on('file-added', (file) => {
      const meta = {
        type: 'homepage_slide',
        slide: index,
      }
      console.log(`File added ${file.id}`, meta)
      uppy.setFileMeta(file.id, meta);
    });
    uppy.on('upload-success', (file, response) => {
      if (response.status === 200 && response.body?.url) {
        onChange({
          ...slide,
          image: response.body?.url,
        })
      }
    })
  }, [])

  const handleTextChange = (e) => {
    text.current = e.target.value;
    onChange({
      ...slide,
      text: e.target.value,
    })
  };

  const handleTextBlur = () => {
    // TODO Sanitize
  };

  const handleTitleChange = (e) => {
    title.current = e.target.value;
    onChange({
      ...slide,
      title: e.target.value,
    })
  };

  const handleTitleBlur = () => {
    // TODO Sanitize
  };

  const handleButtonTextChange = (e) => {
    buttonText.current = e.target.value;
    onChange({
      ...slide,
      buttonText: e.target.value,
    })
  };

  const handleButtonTextBlur = () => {
    // TODO Sanitize
  };

  return (
    <a href="#" onClick={(e) => e.preventDefault()} style={{ backgroundColor: slide.backgroundColor || '#ffffff' }}>
      <div className="swiper-slide-content">
        <div className="swiper-slide-content-left">
          <ContentEditable
            tagName="h4"
            html={title.current}
            onBlur={handleTitleBlur}
            onChange={handleTitleChange} />
          <ContentEditable
            tagName="p"
            html={text.current}
            onBlur={handleTextBlur}
            onChange={handleTextChange} />
          <button type="button" className="btn btn-xs">
            <ContentEditable
              tagName="span"
              html={buttonText.current}
              onBlur={handleButtonTextBlur}
              onChange={handleButtonTextChange} />
          </button>
        </div>
        <div className="swiper-slide-content-right">
          { slide.image ? (
            <img src={slide.image} />
          ) : null}
          <span className="swiper-image-upload">
            <Button id={uniqueKey} className="swiper-image-upload-button" shape="circle" icon={<UploadOutlined />} />
          </span>
        </div>
      </div>
    </a>
  )
}

const SliderEditor = ({ defaultSlides, onChange, uploadEndpoint }) => {

  const [slides, setSlides] = useState(defaultSlides)

  useEffect(() => {
    onChange(slides)
  }, [slides, onChange])

  console.log('SliderEditor.render()')

  return (
    <Swiper
      wrapperTag="ol"
      className="swiper-homepage"
      spaceBetween={20}
      slidesPerView={3}
    >
      { slides.map((slide, index) => {
        return (
          <SwiperSlide
            tag="li"
            key={`slide-${index}`}>
            <span className="swiper-slide-toolbar">
              <Flex>
                <Tooltip title="Link">
                  <Popover
                    title="Link"
                    trigger="click"
                    content={() => {
                      return (
                        <Input placeholder="http://" value={slide.href} onChange={(e) => {
                          const newSlides = [...slides]
                            newSlides.splice(index, 1, {
                              ...slides[index],
                              href: e.target.value
                          })
                          setSlides(newSlides)
                        }} />
                      )
                    }}>
                    <Button type="text" size="small" shape="circle" icon={<LinkOutlined />} />
                  </Popover>
                </Tooltip>
                <Tooltip title="Color">
                  <ColorPicker defaultValue={ slide.backgroundColor || '#ffffff' } size="small" onChange={(color) => {
                    const newSlides = [...slides]
                      newSlides.splice(index, 1, {
                        ...slides[index],
                        backgroundColor: color.toHexString()
                    })
                    setSlides(newSlides)
                  }} />
                </Tooltip>
                <Tooltip title="Delete">
                  <Button type="text" size="small" shape="circle" icon={<CloseOutlined />} onClick={() => {
                    const newSlides = [...slides]
                    newSlides.splice(index, 1)
                    setSlides(newSlides)
                  }} />
                </Tooltip>
              </Flex>
            </span>
            <SlideContent
              uploadEndpoint={uploadEndpoint}
              slide={slide}
              index={index}
              onChange={(s) => {
                console.log(`Slide ${index} has changed`, s)
                const newSlides = [...slides]
                newSlides.splice(index, 1, {
                  ...slides[index],
                  ...s,
                })
                setSlides(newSlides)
              }} />
          </SwiperSlide>
        )
      }) }
      <SwiperSlide tag="li" className="swiper-slide-last">
        <LastSlideContent onClick={() => {
          setSlides([
            ...slides,
            {
              href: '#',
              title: 'New title',
              text: 'New slide',
              buttonText: 'Learn more',
              backgroundColor: '#ffffff',
              image: null,
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
        backgroundColor: '#ffffff',
        image: null,
      },
      {
        href: '#',
        title: 'Title 2',
        text: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit',
        buttonText: 'Learn more',
        backgroundColor: '#ffffff',
        image: null,
      },
    ]
  }

  render() {

    const wrapper = document.createElement('div');
    wrapper.style.marginBottom = '20px'

    const sliderEditorEl = document.createElement('div');

    createRoot(sliderEditorEl).render(<SliderEditor
      uploadEndpoint={this.config.uploadEndpoint}
      defaultSlides={this.slides}
      onChange={(slides) => {
        console.log('Slides updated', slides)
        this.slides = slides
      }} />)

    wrapper.appendChild(sliderEditorEl)

    return wrapper;
  }

  save(blockContent) {
    return {
      slides: this.slides
    }
  }
}
