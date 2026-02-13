import React, { useEffect, useState } from 'react'
import { createRoot } from 'react-dom/client'

import Swiper from 'swiper'
import { Navigation } from 'swiper/modules'
import _ from 'lodash'
import qs from 'qs'
import { Cascader } from 'antd';

const httpClient = new window._auth.httpClient();

// TODO
// Do not duplicate code
// At the same time, breakpoints should be reduced for EditorJS width
const swiperOpts = {
  modules: [ Navigation ],
  slidesPerView: 'auto',
  slidesPerGroup: 1,
  spaceBetween: 20,
  navigation: {
    nextEl: '.swiper-nav-next',
    prevEl: '.swiper-nav-prev',
  },
  lazyLoading: true,
  // https://getbootstrap.com/docs/5.3/layout/breakpoints/
  breakpoints: {
    // Small devices (landscape phones, 576px and up)
    576: {
      slidesPerView: 2,
    },
    // Medium devices (tablets, 768px and up)
    768: {
      slidesPerView: 3,
    },
    // // Large devices (desktops, 992px and up)
    // 992: {
    //   slidesPerView: 4,
    // },
    // // X-Large devices (large desktops, 1200px and up)
    // 1200: {
    //   slidesPerView: 6,
    // }
  },
  observer: true, // to be initialized properly inside a hidden container
  observeParents: true,
}

let optionsCache = []
let isFetchingCollections = false
let fetchCollectionsListeners = []

async function fetchCollections(cuisines, setOptions) {

  if (optionsCache.length > 0) {
    setOptions(optionsCache)
    return
  }

  // If another fetch is already running,
  // stack a listener that will be called later
  if (isFetchingCollections) {
    fetchCollectionsListeners.push(setOptions)
    return
  }

  isFetchingCollections = true

  const { response, error } = await httpClient.get('/api/shop_collections');
  const collections = response['hydra:member']

  isFetchingCollections = false

  const otherOptions = []
  if (collections.length > 0) {
    otherOptions.push({
      label: 'Custom',
      value: 'custom',
      children: collections.map((c) => ({
        label: c.title,
        value: qs.stringify({ slug: c.slug }),
      }))
    })
  }

  const options = [
    {
      label: 'New',
      value: 'newest'
    },
    {
      label: 'Featured',
      value: 'featured'
    },
    {
      label: 'Exclusive',
      value: 'exclusive'
    },
    {
      label: 'Cuisine',
      value: 'cuisine',
      children: cuisines.map(({ label, value }) => ({
        label,
        value: qs.stringify({ cuisine: value })
      }))
    },
    ...otherOptions
  ]

  optionsCache = options

  setOptions(options)

  fetchCollectionsListeners.forEach((listener) => listener.call(null, options))
}

const ComponentCascader = ({ cuisines, onChange, defaultValue }) => {

  const [options, setOptions] = useState([])

  useEffect(() => {
    fetchCollections(cuisines, setOptions)
  }, [setOptions]);

  if (options.length === 0) {
    // TODO Show loader
    return null;
  }

  return (
    <Cascader
      defaultValue={defaultValue}
      options={options}
      onChange={onChange}
      placeholder="Please select" />
  )
}

export default class ShopCollection {

  static get toolbox() {
    return {
      title: 'Shop Collection',
      // https://lucide.dev/icons/utensils
      icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-utensils-icon lucide-utensils"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/></svg>'
    };
  }

  constructor({ data, config }){
    this.data = data;
    this.config = config;
    console.log(this)
  }

  showPreview(wrapper, component, args = '') {

    console.log('showPreview', component, args)

    wrapper.classList.add('cdx-loader')

    // /admin/shop-collections/{id}/preview
    httpClient.get(`//${window.location.host}/admin/shop-collections/preview/${component}?${args}`).then(({ response, error }) => {

      if (error) {
        return;
      }

      const collWrapper = document.createElement('div');
      collWrapper.innerHTML = response
      wrapper.appendChild(collWrapper)

      const swiper = new Swiper(collWrapper.querySelector('.swiper'), swiperOpts);

      wrapper.classList.remove('cdx-loader')
    })
  }

  _getDefaultValue() {
    if (this.data && this.data.component) {

      const value = [
        this.data.component
      ];

      if (_.isObject(this.data.args) && _.size(this.data.args) > 0) {
        value.push(qs.stringify(this.data.args))
      }

      return value
    }

    return []
  }

  render() {

    console.log('render', this.data)

    const wrapper = document.createElement('div');
    wrapper.style.marginBottom = '20px'

    const cascader = document.createElement('div')
    createRoot(cascader).render(
      <ComponentCascader
        defaultValue={ this._getDefaultValue()  }
        cuisines={this.config.cuisines}
        onChange={(value) => {
          const [ component, args ] = value
          this.showPreview(wrapper, component, args)

          this._data = {
            component,
            args: args ? qs.parse(args) : {}
          }
        }} />
    )

    if (this.data.component) {
      this._data = this.data
      this.showPreview(wrapper, this.data.component,
        _.isObject(this.data.args) && _.size(this.data.args) > 0 ? qs.stringify(this.data.args) : '')
    }

    wrapper.appendChild(cascader)

    return wrapper;
  }

  save(blockContent) {
    return this._data
  }
}
