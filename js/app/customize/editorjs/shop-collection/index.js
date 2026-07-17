import React, { useEffect, useState, useMemo } from 'react'
import { createRoot } from 'react-dom/client'

import Swiper from 'swiper'
import { Navigation } from 'swiper/modules'
import _ from 'lodash'
import qs from 'qs'
import { Cascader, Skeleton, Card, Space } from 'antd';
import { useTranslation } from 'react-i18next';

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

const ComponentCascader = ({ placeholder, cuisines, customCollections, edenredEnabled, onChange, defaultValue }) => {

  const { t } = useTranslation();

  const options = useMemo(() => {

    const otherOptions = []
    otherOptions.push({
      label: 'Custom',
      value: 'custom',
      children: customCollections.map((c) => ({
        label: c.title,
        value: qs.stringify({ slug: c.slug }),
      })).concat([{
        label: t('ADD_BUTTON'),
        value: ''
      }])
    })

    return [
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
      ...(edenredEnabled ? [{
        label: 'Edenred',
        value: 'edenred'
      }] : []),
      {
        label: 'Zero Waste',
        value: 'zerowaste'
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

  }, [customCollections, edenredEnabled])

  return (
    <Cascader
      defaultValue={defaultValue}
      options={options}
      onChange={onChange}
      placeholder={placeholder}
      style={{ width: '100%' }} />
  )
}

const SORT_OPTIONS = [
  'historical_order_volume',
  'ordering_potential',
  'popularity',
]

const SortOptionLabel = ({ title, help }) => (
  <div>
    <div>{title}</div>
    <div style={{ fontSize: '12px', color: 'rgba(0, 0, 0, 0.45)', whiteSpace: 'normal' }}>{help}</div>
  </div>
)

const SortSelect = ({ defaultValue, onChange }) => {

  const { t } = useTranslation();
  const tPrefix = 'HOMEPAGE_EDITOR.messages.tools.shop_collection.'

  const buildOption = (value, key) => {
    const title = t(`${tPrefix}sort_${key}`)
    const help = t(`${tPrefix}sort_${key}_help`)

    return {
      value,
      // plain-text title, used for the closed control via displayRender below
      title,
      // richer node, shown in the dropdown panel only
      label: <SortOptionLabel title={title} help={help} />,
    }
  }

  const options = [
    buildOption('', 'default'),
    ...SORT_OPTIONS.map((key) => buildOption(key, key)),
  ]

  return (
    <Cascader
      defaultValue={defaultValue ? [defaultValue] : undefined}
      options={options}
      onChange={(value) => onChange((value && value[0]) || undefined)}
      displayRender={(labels, selectedOptions) =>
        (selectedOptions && selectedOptions[0]) ? selectedOptions[0].title : labels.join(' / ')}
      placeholder={t(`${tPrefix}sort_placeholder`)}
      style={{ width: '100%' }} />
  )
}

function showSkeleton(wrapper) {
  const container = document.createElement('div');
  createRoot(container).render(
    <div className="ssc p-3" data-loader>
      <div className="align-start flex justify-between mb">
        <div className="ssc-head-line w-40"></div>
        <div className="ssc-head-line w-20"></div>
      </div>
      <div className="align-start flex justify-between">
        <div className="ssc-card ssc-wrapper w-30">
          <div className="mb ssc-head-line"></div>
          <div className="mbs ssc-line w-80"></div>
          <div className="mbs ssc-line w-40"></div>
          <div className="mbs ssc-line w-60"></div>
        </div>
        <div className="ssc-card ssc-wrapper w-30">
          <div className="mb ssc-head-line"></div>
          <div className="mbs ssc-line w-80"></div>
          <div className="mbs ssc-line w-40"></div>
          <div className="mbs ssc-line w-60"></div>
        </div>
        <div className="ssc-card ssc-wrapper w-30">
          <div className="mb ssc-head-line"></div>
          <div className="mbs ssc-line w-80"></div>
          <div className="mbs ssc-line w-40"></div>
          <div className="mbs ssc-line w-60"></div>
        </div>
      </div>
    </div>
  )
  wrapper.appendChild(container)
}

function hideSkeleton(wrapper) {
  wrapper.querySelector('[data-loader]').remove()
}

export default class ShopCollection {

  static get toolbox() {
    return {
      title: 'Shop Collection',
      // https://lucide.dev/icons/utensils
      icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" className="lucide lucide-utensils-icon lucide-utensils"><path d="M3 2v7c0 1.1.9 2 2 2h4a2 2 0 0 0 2-2V2"/><path d="M7 2v20"/><path d="M21 15V2a5 5 0 0 0-5 5v6c0 1.1.9 2 2 2h3Zm0 0v7"/></svg>'
    };
  }

  constructor({ data, config, api }){
    this.data = data;
    this.config = config;
    this.api = api;
    this.httpClient = new window._auth.httpClient();
  }

  showPreview(wrapper, component, args = '') {

    const preview = wrapper.querySelector('[data-preview]')
    if (preview) {
      preview.remove();
    }

    wrapper.classList.add('cdx-loader')
    showSkeleton(wrapper)

    this.httpClient.get(`//${window.location.host}/admin/shop-collections/preview/${component}?${args}`).then(({ response, error }) => {

      if (error) {
        return;
      }

      const collWrapper = document.createElement('div');
      collWrapper.setAttribute('data-preview', '')
      collWrapper.innerHTML = response
      wrapper.appendChild(collWrapper)

      const swiper = new Swiper(collWrapper.querySelector('.swiper'), swiperOpts);

      wrapper.classList.remove('cdx-loader')
      hideSkeleton(wrapper)
    })
  }

  _getDefaultValue() {
    if (this.data && this.data.component) {

      const value = [
        this.data.component
      ];

      // sort is handled by its own control, not part of the Cascader's value
      const args = _.isObject(this.data.args) ? _.omit(this.data.args, 'sort') : {}

      if (_.size(args) > 0) {
        value.push(qs.stringify(args))
      }

      return value
    }

    return []
  }

  render() {

    const wrapper = document.createElement('div');
    wrapper.style.marginBottom = '20px'

    const controls = document.createElement('div')
    controls.style.display = 'flex'
    controls.style.gap = '10px'

    // Tracks the currently selected component/args/sort outside of React state,
    // since the two controls (Cascader + sort Select) are mounted as separate
    // React roots and both need to contribute to the same preview + saved data.
    let currentComponent = this.data ? this.data.component : undefined
    let currentArgs = (this.data && _.isObject(this.data.args)) ? _.omit(this.data.args, 'sort') : {}
    let currentSort = (this.data && this.data.args) ? this.data.args.sort : undefined

    const updatePreviewAndData = () => {
      if (!currentComponent) {
        return
      }
      const args = { ...currentArgs, ...(currentSort ? { sort: currentSort } : {}) }
      this._data = { component: currentComponent, args }
      this.showPreview(wrapper, currentComponent, _.size(args) > 0 ? qs.stringify(args) : '')
    }

    const cascader = document.createElement('div')
    cascader.style.flex = '1'
    createRoot(cascader).render(
      <ComponentCascader
        defaultValue={ this._getDefaultValue()  }
        cuisines={this.config.cuisines}
        customCollections={this.config.customCollections}
        edenredEnabled={this.config.edenredEnabled}
        placeholder={this.api.i18n.t('select_value')}
        onChange={(value) => {

          const [ component, args ] = value

          if (component === 'custom' && args === '') {
            window.open(window.Routing.generate('admin_customize_shop_collections'), '_blank').focus();
          } else {
            currentComponent = component
            currentArgs = args ? qs.parse(args) : {}
            updatePreviewAndData()
          }

        }} />
    )

    const sortSelect = document.createElement('div')
    sortSelect.style.flex = '1'
    createRoot(sortSelect).render(
      <SortSelect
        defaultValue={ currentSort }
        onChange={(value) => {
          currentSort = value
          updatePreviewAndData()
        }} />
    )

    controls.appendChild(cascader)
    controls.appendChild(sortSelect)
    wrapper.appendChild(controls)

    if (this.data.component) {
      updatePreviewAndData()
    }

    return wrapper;
  }

  save(blockContent) {
    return this._data
  }
}
