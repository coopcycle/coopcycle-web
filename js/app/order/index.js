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

import { MantineProvider } from '@mantine/core';
import { LoadingOverlay } from '@mantine/core';

import i18n, { getCurrencySymbol } from '../i18n'
import LoopeatModal from './LoopeatModal'

import './index.scss'
import '../components/order/index.scss'

// https://mantine.dev/styles/mantine-styles/#css-layers
import '@mantine/core/styles.layer.css';
import '@mantine/core/styles/LoadingOverlay.layer.css';

import { disableBtn, enableBtn } from '../widgets/button'
import { createStoreFromPreloadedState } from './redux/store'
import {
  checkTimeRange,
  getTimingPathForStorage,
} from '../utils/order/helpers'
import TimeRangeChangedModal
  from '../components/order/timeRange/TimeRangeChangedModal'
import TimeRange from '../components/order/timeRange/TimeRange'
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

function setLoading(isLoading) {

  loadingOverlayRoot.render(
    <MantineProvider>
      <LoadingOverlay visible={isLoading} zIndex={1} overlayProps={{ radius: "sm", blur: 2 }} />
    </MantineProvider>
  )
  if (isLoading) {
    disableBtn(submitPageBtn)
  } else {
    enableBtn(submitPageBtn)
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

      $('#modal-vytal').modal('show');

    } else {
      submitForm();
    }

  });
}

$('#modal-vytal').on('hidden.bs.modal', function() {
  document.querySelector('#checkout_address_reusablePackagingEnabled').checked = false
});

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

const form = document.querySelector('form[name="checkout_address"]')

form.addEventListener('submit', async function(event) {
  event.preventDefault()

  submitPageBtn.classList.add('btn--loading')
  setLoading(true)

  const shippingTimeRange = selectShippingTimeRange(store.getState())
  const persistedTimeRange = selectPersistedTimeRange(store.getState())

  // if the customer has already selected the time range, it will be checked on the server side
  if (!shippingTimeRange && persistedTimeRange) {

    try {
      await checkTimeRange(persistedTimeRange, store.getState, store.dispatch)
    } catch (error) {
      submitPageBtn.classList.remove('btn--loading')
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
    </I18nextProvider>
  </Provider>
)
