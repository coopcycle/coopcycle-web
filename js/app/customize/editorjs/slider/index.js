import React, { useEffect, useState, createRef, useRef } from 'react'
import { createRoot } from 'react-dom/client'
import { Swiper, SwiperSlide, useSwiper } from 'swiper/react';
import {
  CloseOutlined,
  EditOutlined,
  PlusSquareOutlined,
  LinkOutlined,
  UploadOutlined,
  CalendarOutlined,
  CalendarTwoTone,
  LeftOutlined,
  RightOutlined,
} from '@ant-design/icons';
import { Button, Flex, Tooltip, Popover, Input, DatePicker } from 'antd';
import sanitizeHtml from 'sanitize-html';
import ContentEditable from 'react-contenteditable'
import Uppy from '@uppy/core'
import Dashboard from '@uppy/dashboard';
import XHR from '@uppy/xhr-upload';
import { ArrowRight } from 'lucide-react';
import moment from 'moment'

import ColorPicker from '../../color-picker';

function unescapeHTML(string) {
  const el = document.createElement("span");
  el.innerHTML = string;
  return el.innerText;
}

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

function handleContentEditableChange(e, ref) {
  ref.current = e.target.value;
}

function handleContentEditableBlur(slide, ref, prop, onChange) {
  ref.current = sanitizeHtml(unescapeHTML(ref.current), {
    // https://github.com/apostrophecms/sanitize-html?tab=readme-ov-file#what-if-i-dont-want-to-allow-any-tags
    allowedTags: [],
    allowedAttributes: {},
  });
  onChange({
    ...slide,
    [prop]: ref.current,
  })
}

const isExpired = (slide) => slide.expiresAt ? moment().isAfter(slide.expiresAt) : false;

const SlideContent = ({ slide, index, uploadEndpoint, onChange }) => {

  const title = useRef(slide.title);
  const text = useRef(slide.text);
  const buttonText = useRef(slide.buttonText);

  useEffect(() => {
    title.current = slide.title
    text.current = slide.text
    buttonText.current = slide.buttonText
  }, [ slide ])

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

  return (
    <a href="#" onClick={(e) => e.preventDefault()} style={{
      backgroundColor: slide.backgroundColor || '#ffffff',
      opacity: isExpired(slide) ? 0.6 : 1,
    }}>
      <div className={ `swiper-slide-content` }>
        <div className="swiper-slide-content-left" style={{ position: 'relative' }}>
          <ContentEditable
            tagName="h4"
            html={title.current || ''}
            onChange={(e) => handleContentEditableChange(e, title)}
            onBlur={() => handleContentEditableBlur(slide, title, 'title', onChange)}
            className={`coopcycle-homepage-bg-${slide.colorScheme}`} />
          <ContentEditable
            tagName="p"
            html={text.current || ''}
            onChange={(e) => handleContentEditableChange(e, text)}
            onBlur={() => handleContentEditableBlur(slide, text, 'text', onChange)}
            className={`coopcycle-homepage-bg-${slide.colorScheme}`} />
          <button type="button" className="btn btn-xs">
            <ContentEditable
              tagName="span"
              html={buttonText.current || ''}
              onChange={(e) => handleContentEditableChange(e, buttonText)}
              onBlur={() => handleContentEditableBlur(slide, buttonText, 'buttonText', onChange)} />
            <ArrowRight size={12} className="ml-2" />
          </button>
          <div className="swiper-slide-color-picker">
            <Tooltip title="Color">
              <ColorPicker
                value={ slide.backgroundColor || '#ffffff' }
                size="small"
                onChange={(color, css, colorScheme) => {
                  onChange({
                    ...slide,
                    backgroundColor: color.toHexString(),
                    colorScheme: colorScheme,
                  })
                }} />
            </Tooltip>
          </div>
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

const SlideToolbar = ({ slide, index, isLast, onChange, onClickDelete, onClickMoveLeft, onClickMoveRight }) => {
  return (
    <Flex>
      <Tooltip title="Move left">
        <Button type="text" disabled={ index === 0 } size="small" shape="circle" icon={<LeftOutlined />} onClick={onClickMoveLeft} />
      </Tooltip>
      <Tooltip title="Move right">
        <Button type="text" disabled={ isLast } size="small" shape="circle" icon={<RightOutlined />} onClick={onClickMoveRight} />
      </Tooltip>
      <Tooltip title="Expiration">
        <Popover
          title="Expiration date"
          trigger="click"
          content={() => {
            return (
              <DatePicker
                showTime
                defaultValue={ slide.expiresAt ? moment(slide.expiresAt) : null }
                onChange={(value, dateString) => {
                  onChange({
                    ...slide,
                    expiresAt: dateString,
                  })
                }} />
            )
          }}>
          <Button type="text" size="small" shape="circle" icon={
            <CalendarOutlined
              style={ slide.expiresAt ? { color: isExpired(slide) ? 'red' : 'blue' } : null } /> }
            />
        </Popover>
      </Tooltip>
      <Tooltip title="Link">
        <Popover
          title="Link"
          trigger="click"
          content={() => {
            return (
              <Input placeholder="http://" value={slide.href} onChange={(e) => {
                onChange({
                  ...slide,
                  href: e.target.value,
                })
              }} />
            )
          }}>
          <Button type="text" size="small" shape="circle" icon={<LinkOutlined />} />
        </Popover>
      </Tooltip>
      <Tooltip title="Delete">
        <Button type="text" size="small" shape="circle" icon={<CloseOutlined />} onClick={onClickDelete} />
      </Tooltip>
    </Flex>
  )
}

const SliderEditor = ({ defaultSlides, onChange, uploadEndpoint }) => {

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
    >
      { slides.map((slide, index) => {
        return (
          <SwiperSlide
            tag="li"
            key={`slide-${index}`}
            className="swiper-slide-editing">
            <SlideContent
              uploadEndpoint={uploadEndpoint}
              slide={slide}
              index={index}
              onChange={(s) => {
                const newSlides = [...slides]
                newSlides.splice(index, 1, {
                  ...slides[index],
                  ...s,
                })
                setSlides(newSlides)
              }} />
            <span className="swiper-slide-toolbar">
              <SlideToolbar
                slide={slide}
                index={index}
                isLast={slides.length === 0 || index === (slides.length - 1)}
                onChange={(s) => {
                  const newSlides = [...slides]
                  newSlides.splice(index, 1, {
                    ...slides[index],
                    ...s,
                  })
                  setSlides(newSlides)
                }}
                onClickDelete={() => {
                  const newSlides = [...slides]
                  newSlides.splice(index, 1)
                  setSlides(newSlides)
                }}
                onClickMoveLeft={() => {
                  const newSlides = [...slides]
                  newSlides.splice(index - 1, 1, {
                    ...slides[index],
                  })
                  newSlides.splice(index, 1, {
                    ...slides[index - 1],
                  })
                  setSlides(newSlides)
                }}
                onClickMoveRight={() => {
                  const newSlides = [...slides]
                  newSlides.splice(index + 1, 1, {
                    ...slides[index],
                  })
                  newSlides.splice(index, 1, {
                    ...slides[index + 1],
                  })
                  setSlides(newSlides)
                }} />
            </span>
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
              colorScheme: 'light',
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
        colorScheme: 'light',
      },
      {
        href: '#',
        title: 'Title 2',
        text: 'Lorem ipsum dolor sit amet, consectetur adipiscing elit',
        buttonText: 'Learn more',
        backgroundColor: '#ffffff',
        image: null,
        colorScheme: 'light',
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
        console.log('Slides have changed', slides)
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
