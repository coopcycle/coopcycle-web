import Swiper from 'swiper'
import { Navigation } from 'swiper/modules'
import _ from 'lodash'
import qs from 'qs'

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

const sectionTypes = [
  {
    label: 'New',
    component: 'auto:newest'
  },
  {
    label: 'Featured',
    component: 'auto:featured'
  },
  {
    label: 'Exclusive',
    component: 'auto:exclusive'
  },
  {
    label: 'Cuisine',
    component: 'auto:cuisine',
    args: ['cuisine']
  },
]

function hasArgs(component) {
  const hash = _.keyBy(sectionTypes, 'component');
  return !!hash[component]?.args
}

function getArgs(component) {
  const hash = _.keyBy(sectionTypes, 'component');
  return hash[component]?.args || []
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
  }

  addInputsForArg(argName, wrapper, onChange) {
    console.log(`Adding inputs for arg "${argName}"`)

    if (argName === 'cuisine') {
      const select = document.createElement('select');
      select.name = argName;
      // select.classList.add('form-control')

      select.addEventListener('change', (e) => {
        onChange({ cuisine: e.currentTarget.value })
      })

      const defaultOption = document.createElement('option');
      defaultOption.innerHTML = 'Select...'

      select.appendChild(defaultOption)

      this.config.cuisines.forEach((c) => {
        const option = document.createElement('option');
        option.innerHTML = c.label
        option.value = c.value
        select.appendChild(option)
      })
      wrapper.appendChild(select)
    }
  }

  showPreview(selectedValue, wrapper, args = {}) {
    const identifier = !selectedValue.startsWith('auto:') ? selectedValue.match(/([0-9]*)$/gm)[0] : selectedValue;

    wrapper.classList.add('cdx-loader')

    const queryString = !_.isEmpty(args) ? `?${qs.stringify(args)}` : '';

    // /admin/shop-collections/{id}/preview
    httpClient.get(`//${window.location.host}/admin/shop-collections/${identifier}/preview${queryString}`).then(({ response, error }) => {

      if (error) {
        return;
      }

      const collWrapper = document.createElement('div');
      collWrapper.innerHTML = response
      wrapper.appendChild(collWrapper)

      const swiper = new Swiper(collWrapper.querySelector('.swiper'), swiperOpts);

      wrapper.classList.remove('cdx-loader')

      // TODO We can also keep the select, and update on change
      // select.remove()
    })
  }

  render() {

    console.log('render', this.config)

    const wrapper = document.createElement('div');
    wrapper.style.marginBottom = '20px'

    const select = document.createElement('select');
    // select.classList.add('form-control')

    const defaultOption = document.createElement('option');
    defaultOption.innerHTML = 'Select...'

    select.appendChild(defaultOption)

    sectionTypes.forEach((s) => {
      const option = document.createElement('option');
      option.innerHTML = s.label
      option.value = s.component
      select.appendChild(option)
    })

    let collections = [];

    wrapper.classList.add('cdx-loader')

    httpClient.get('/api/shop_collections').then(({ response, error }) => {
      collections = response['hydra:member']
      response['hydra:member'].forEach((c) => {
        const option = document.createElement('option');
        option.innerHTML = c.title
        option.value = c['@id']
        select.appendChild(option)
        wrapper.classList.remove('cdx-loader')
      })
    })

    select.addEventListener('change', (e) => {

      const selectedValue = e.currentTarget.value;

      console.log(`Selected ${selectedValue}`)

      if (hasArgs(selectedValue)) {

        getArgs(selectedValue).forEach((argName) => {
          this.addInputsForArg(argName, wrapper, (args) => {
            this.showPreview(selectedValue, wrapper, args)
          })
        })

        return;

      }

      this.showPreview(selectedValue, wrapper)

    })

    wrapper.appendChild(select)

    return wrapper;
  }

  save(blockContent) {
    return {
      url: blockContent.value
    }
  }
}
