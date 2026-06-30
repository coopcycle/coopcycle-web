import Swiper from 'swiper'
import { Navigation } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/navigation'
import Inputmask from 'inputmask'
import numbro from 'numbro'
import _ from 'lodash'
import React from 'react'
import { createPortal } from 'react-dom'
import Modal from 'react-modal'
import { createRoot } from 'react-dom/client'
import { Provider } from 'react-redux'
import { I18nextProvider } from 'react-i18next'
import axios from 'axios'

import i18n, { getCurrencySymbol } from '../i18n'
import LoopeatModal from './LoopeatModal'

import './index.scss'
import '../components/order/index.scss'

import { disableBtn, enableBtn } from '../widgets/button'
import { createStoreFromPreloadedState } from './redux/store'
import {
  checkTimeRange,
  getTimingPathForStorage,
} from '../utils/order/helpers'
import TimeRangeChangedModal
  from '../components/order/timeRange/TimeRangeChangedModal'
import TimeRange from '../components/order/timeRange/TimeRange'
import ProductOptionsModal from '../restaurant/components/ProductDetails/ProductOptionsModal'
import { openProductOptionsModal, addItem } from '../restaurant/redux/actions'
import { accountSlice } from '../entities/account/reduxSlice'
import { guestSlice } from '../entities/guest/reduxSlice'
import { buildGuestInitialState } from '../entities/guest/utils'
import {
  orderSlice,
  selectShippingTimeRange,
} from '../entities/order/reduxSlice'
import {
  selectPersistedTimeRange,
  timeRangeSlice,
} from '../components/order/timeRange/reduxSlice'

const {
  currency,
  currencyFormat,
  delimiters
} = numbro.languageData()

const space = currencyFormat.spaceSeparatedCurrency ? ' ' : ''

var im = new Inputmask("currency", {
  radixPoint: delimiters.decimal,
  suffix: currency.position === 'postfix' ? `${space}${getCurrencySymbol()}` : '',
  allowMinus: false,
})

const submitPageBtn = document.querySelector('.btn-submit-page')

const getValue = (inputmask) => numbro.unformat(inputmask.unmaskedvalue())

function enableTipInput() {
  im.mask('#tip-input')
  const tipInput = document.querySelector('#tip-input')
  tipInput.addEventListener('change', updateTip)
  tipInput.addEventListener('keydown', function(e) {
    if (e.keyCode == 13) {
      e.preventDefault()
      e.target.blur()
    }
  })
}

const loadingOverlayRoot = createRoot(document.getElementById('loading-overlay'))

function SimpleLoadingOverlay({ visible }) {
  if (!visible) return null
  return (
    <div className="absolute inset-0 flex items-center justify-center bg-base-100/60 backdrop-blur-sm" style={{ zIndex: 1 }}>
      <span className="loading loading-spinner loading-lg"></span>
    </div>
  )
}

function setLoading(isLoading) {

  loadingOverlayRoot.render(<SimpleLoadingOverlay visible={isLoading} />)
  if (isLoading) {
    disableBtn(submitPageBtn)
    submitPageBtn.querySelector('.loading').classList.remove('hidden')
  } else {
    enableBtn(submitPageBtn)
    submitPageBtn.querySelector('.loading').classList.add('hidden')
  }
}

const replaceOrderTable = (html) => {
  const parser = new DOMParser()
  const doc = parser.parseFromString(html, 'text/html')
  const newTable = doc.querySelector('form[name="checkout_address"] table')
  document.querySelector('form[name="checkout_address"] table').replaceWith(newTable)
}

const updateTip = _.debounce(async function() {

  const mask = document.querySelector('#tip-input').inputmask
  const newValue = mask.unmaskedvalue()

  const tipForm = document.querySelector('form[name="checkout_tip"]')

  const params = new URLSearchParams()
  params.append('checkout_tip[amount]', newValue)
  params.append('checkout_tip[_token]', document.querySelector('#checkout_tip__token').value)

  setLoading(true)

  try {
    const { data: html } = await axios.post(tipForm.action, params, { withCredentials: true })
    replaceOrderTable(html)
    enableTipInput()
  } finally {
    setLoading(false)
  }

}, 350)

function submitForm() {
  document.querySelector('#checkout_address_reusablePackagingEnabled').closest('form').submit()
}

const loopeatModal = document.querySelector('#modal-loopeat');
const loopeatModalOpener = document.querySelector('[data-target="#modal-loopeat"]');

if (loopeatModal && loopeatModalOpener) {

  const loopeatModalRoot =
    createRoot(loopeatModal.querySelector('.modal-body [data-widget="loopeat-returns"]'))

  loopeatModalOpener.addEventListener('click', function (e) {

    const customerContainers = JSON.parse(e.currentTarget.dataset.customerContainers)
    const formats = JSON.parse(e.currentTarget.dataset.formats)
    const formatsToDeliver = JSON.parse(e.currentTarget.dataset.formatsToDeliver)
    const returns = JSON.parse(e.currentTarget.dataset.returns)
    const creditsCountCents = JSON.parse(e.currentTarget.dataset.creditsCountCents)
    const requiredAmount = JSON.parse(e.currentTarget.dataset.requiredAmount)
    const containersCount = JSON.parse(e.currentTarget.dataset.containersCount)
    const oauthUrl = e.currentTarget.dataset.oauthUrl

    loopeatModalRoot.render(<LoopeatModal
      customerContainers={ customerContainers }
      formats={ formats }
      formatsToDeliver={ formatsToDeliver }
      initialReturns={ returns }
      creditsCountCents={ creditsCountCents }
      requiredAmount={ requiredAmount }
      containersCount={ containersCount }
      oauthUrl={ oauthUrl }
      closeModal={ () => loopeatModal.close() }
      onChange={ returns => {
        document.querySelector('#loopeat_returns_returns').value = JSON.stringify(returns)
      }}
      onSubmit={ () => {
        document.querySelector('form[name="loopeat_returns"]').submit()
      }} />)

    loopeatModal.showModal();

  });
}

const loopeatHowItWorksModal = document.querySelector('modal-loopeat-howitworks');
if (loopeatHowItWorksModal) {
  document.querySelector('[data-target="#modal-loopeat-howitworks"]').addEventListener('click', (e) => {
    e.preventDefault();
    // There is no "open" event on dialog element,
    // so we need to use JavaScript to track event when modal is openn
    loopeatHowItWorksModal.openModal();
    window._paq.push(['trackEvent', 'Checkout', 'openModal', 'zeroWasteHowItWorks']);
  });
}

const reusablePackagingEnabled = document.querySelector('#checkout_address_reusablePackagingEnabled');

if (reusablePackagingEnabled) {
  reusablePackagingEnabled.addEventListener('change', function() {
    var isChecked = this.checked
    var isVytal = this.dataset.vytal === 'true'

    window._paq.push(['trackEvent', 'Checkout', (isChecked ? 'zeroWasteEnable' : 'zeroWasteDisable')]);

    if (isVytal) {

      document.querySelector('#modal-vytal').showModal();

    } else {
      submitForm();
    }

  });
}

const vytalModal = document.querySelector('#modal-vytal');
if (vytalModal) {
  vytalModal.addEventListener('close', function() {
    document.querySelector('#checkout_address_reusablePackagingEnabled').checked = false
  });
}

// ---

enableTipInput()

document.querySelector('form[name="checkout_address"]').addEventListener('click', function(e) {
  if (!e.target.closest('#tip-incr')) return
  e.preventDefault()

  const mask = document.querySelector('#tip-input').inputmask
  mask.setValue(getValue(mask) + 1.00)

  updateTip()
})

document.querySelector('#apply-coupon').addEventListener('click', async function(e) {

  e.preventDefault()

  const couponForm = document.querySelector('form[name="checkout_coupon"]')

  const params = new URLSearchParams()
  params.append('checkout_coupon[promotionCoupon]', document.querySelector('#coupon-code').value)
  params.append('checkout_coupon[_token]', document.querySelector('#checkout_coupon__token').value)

  setLoading(true)

  try {
    const { data: html } = await axios.post(couponForm.action, params, { withCredentials: true })
    replaceOrderTable(html)
    enableTipInput()
    document.querySelector('#coupon-code').value = ''
  } finally {
    setLoading(false)
  }
})

const orderDataElement = document.querySelector('#js-order-data')
const orderNodeId = orderDataElement.dataset.orderNodeId
const orderAccessToken = orderDataElement.dataset.orderAccessToken

const buildInitialState = () => {
  const shippingTimeRange = JSON.parse(orderDataElement.dataset.orderShippingTimeRange || null)
  const persistedTimeRange = JSON.parse(window.sessionStorage.getItem(getTimingPathForStorage(orderNodeId)))

  return {
    [accountSlice.name]: accountSlice.getInitialState(),
    [guestSlice.name]: buildGuestInitialState(orderNodeId, orderAccessToken),
    [orderSlice.name]: {
      ...orderSlice.getInitialState(),
      '@id': orderNodeId,
      shippingTimeRange: shippingTimeRange,
    },
    [timeRangeSlice.name]: {
      ...timeRangeSlice.getInitialState(),
      persistedTimeRange: persistedTimeRange,
    }
  }
}

const store = createStoreFromPreloadedState(buildInitialState())

// Product recommendations: open options modal or add simple product
document.addEventListener('click', (e) => {
  const productSimple = e.target.closest('[data-product-simple]')
  if (productSimple) {
    window._paq.push(['trackEvent', 'Checkout', 'addRecommendedItem'])
    store.dispatch(addItem(productSimple.dataset.formAction, 1))
    return
  }

  const productDetails = e.target.closest('[data-modal="product-details"]')
  if (productDetails) {
    const product    = JSON.parse(productDetails.dataset.product)
    const options    = JSON.parse(productDetails.dataset.productOptions)
    const images     = JSON.parse(productDetails.dataset.productImages)
    const price      = JSON.parse(productDetails.dataset.productPrice)
    const formAction = productDetails.dataset.formAction
    store.dispatch(openProductOptionsModal(product, options, images, price, formAction))
  }
})

// Reload after product is added via the options modal so the server-rendered cart updates
let prevModalOpen = false
store.subscribe(() => {
  const state = store.getState()
  const modalOpen = state.isProductOptionsModalOpen
  const hasAdd    = state.lastAddItemRequest !== null

  if (prevModalOpen && !modalOpen && hasAdd) {
    window._paq.push(['trackEvent', 'Checkout', 'addRecommendedItem'])
    window.location.reload()
  }
  prevModalOpen = modalOpen
})

// Initialize Swiper for recommendations once the lazy component has rendered
const swiperObserver = new MutationObserver(() => {
  const el = document.querySelector('.recommendations-swiper')
  if (el && !el.swiper) {
    new Swiper(el, {
      modules: [Navigation],
      slidesPerView: 1,
      slidesPerGroup: 1,
      spaceBetween: 12,
      navigation: {
        nextEl: '.swiper-button-next',
        prevEl: '.swiper-button-prev',
      },
      breakpoints: {
        576: { slidesPerView: 2 },
        768: { slidesPerView: 3 },
      },
    })
    swiperObserver.disconnect()
  }
})
swiperObserver.observe(document.body, { childList: true, subtree: true })

const form = document.querySelector('form[name="checkout_address"]')

form.addEventListener('submit', async function(event) {
  event.preventDefault()

  setLoading(true)

  const shippingTimeRange = selectShippingTimeRange(store.getState())
  const persistedTimeRange = selectPersistedTimeRange(store.getState())

  // if the customer has already selected the time range, it will be checked on the server side
  if (!shippingTimeRange && persistedTimeRange) {

    try {
      await checkTimeRange(persistedTimeRange, store.getState, store.dispatch)
    } catch (error) {
      setLoading(false)
      return
    }
  }

  form.submit()
})

const container = document.getElementById('react-root')

const fulfilmentTimeRangeContainer = document.getElementById('order__fulfilment_time_range__container')

Modal.setAppElement(container)

const root = createRoot(container);
root.render(
  <Provider store={ store }>
    <I18nextProvider i18n={ i18n }>
      {createPortal(<TimeRange />, fulfilmentTimeRangeContainer) }
      <TimeRangeChangedModal />
      <ProductOptionsModal />
    </I18nextProvider>
  </Provider>
)
