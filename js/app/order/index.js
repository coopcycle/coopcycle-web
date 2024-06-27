import Inputmask from 'inputmask'
import numbro from 'numbro'
import _ from 'lodash'
import React from 'react'
import { createPortal, render } from 'react-dom'
import Modal from 'react-modal'
import { createRoot } from 'react-dom/client'
import { Provider } from 'react-redux'
import { I18nextProvider } from 'react-i18next'
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

require('gasparesganga-jquery-loading-overlay')

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
  $('#tip-input').on('change', updateTip)
  $('#tip-input').on('keydown', function(e) {
    if (e.keyCode == 13) {
      e.preventDefault()
      e.target.blur()
    }
  })
}

function setLoading(isLoading) {
  if (isLoading) {
    $('.form-content').LoadingOverlay('show', {
      image: false,
      zIndex: 1,
    })
    disableBtn(submitPageBtn)
  } else {
    $('.form-content').LoadingOverlay('hide')
    enableBtn(submitPageBtn)
  }
}

const updateTip = _.debounce(function() {

  const mask = document.querySelector('#tip-input').inputmask
  const newValue = mask.unmaskedvalue()

  var $form = $('form[name="checkout_tip"]')

  var data = {}
  data['checkout_tip[amount]'] = newValue
  data['checkout_tip[_token]'] = $('#checkout_tip__token').val()

  setLoading(true)

  $.ajax({
    url : $form.attr('action'),
    type: $form.attr('method'),
    data : data,
    success: function(html) {
      $('form[name="checkout_address"] table').replaceWith(
        $(html).find('form[name="checkout_address"] table')
      )

      enableTipInput()

      setLoading(false)
    }
  })

}, 350)

function submitForm() {
  $('#checkout_address_reusablePackagingEnabled').closest('form').submit();
}

$('#modal-loopeat').on('shown.bs.modal', function(e) {
  const customerContainers = JSON.parse(e.relatedTarget.dataset.customerContainers)
  const formats = JSON.parse(e.relatedTarget.dataset.formats)
  const formatsToDeliver = JSON.parse(e.relatedTarget.dataset.formatsToDeliver)
  const returns = JSON.parse(e.relatedTarget.dataset.returns)
  const creditsCountCents = JSON.parse(e.relatedTarget.dataset.creditsCountCents)
  const requiredAmount = JSON.parse(e.relatedTarget.dataset.requiredAmount)
  const containersCount = JSON.parse(e.relatedTarget.dataset.containersCount)
  const oauthUrl = e.relatedTarget.dataset.oauthUrl

  render(<LoopeatModal
    customerContainers={ customerContainers }
    formats={ formats }
    formatsToDeliver={ formatsToDeliver }
    initialReturns={ returns }
    creditsCountCents={ creditsCountCents }
    requiredAmount={ requiredAmount }
    containersCount={ containersCount }
    oauthUrl={ oauthUrl }
    closeModal={ () => $('#modal-loopeat').modal('hide') }
    onChange={ returns => {
      $('#loopeat_returns_returns').val(
        JSON.stringify(returns)
      )
    }}
    onSubmit={ () => {
      document.querySelector('form[name="loopeat_returns"]').submit()
    }} />, this.querySelector('.modal-body [data-widget="loopeat-returns"]'))
});

$('#dabba-add-credit').on('click', function(e) {
  e.preventDefault();

  var expectedWallet = $('#checkout_address_reusablePackagingEnabled').data('dabbaExpectedWallet');
  var authorizeUrl = $('#checkout_address_reusablePackagingEnabled').data('dabbaAuthorizeUrl');

  $('#modal-dabba-redirect-warning [data-continue]')
    .off('click')
    .on('click', () => window.location.href = authorizeUrl + '&expected_wallet='+expectedWallet)
  $('#modal-dabba-redirect-warning').modal('show');
});

$('#checkout_address_cancelReusablePackaging').on('click', function() {
  $('#checkout_address_reusablePackagingEnabled').prop('checked', false);
  submitForm();
});

$('#checkout_address_reusablePackagingPledgeReturn').on('change', _.debounce(function() {
  submitForm();
}, 350));

$('#checkout_address_reusablePackagingEnabled').on('change', function() {
  var isChecked = $(this).is(':checked');
  var isVytal = $(this).data('vytal') === true;
  var isDabba = $(this).data('dabba') === true;
  var expectedWallet = $(this).data('dabbaExpectedWallet');
  var hasDabbaCredentials = $(this).data('dabbaCredentials') === true;
  var dabbaAuthorizeUrl = $(this).data('dabbaAuthorizeUrl') + `&expected_wallet=${expectedWallet}`;

  if (isVytal) {

    $('#modal-vytal').modal('show');

  } else if (isDabba && !hasDabbaCredentials && isChecked) {

    $('#modal-dabba-redirect-warning [data-continue]')
      .off('click')
      .on('click', () => window.location.href = dabbaAuthorizeUrl)
    $('#modal-dabba-redirect-warning').modal('show');

  } else {
    submitForm();
  }

});

$('#modal-vytal').on('hidden.bs.modal', function() {
  $('#checkout_address_reusablePackagingEnabled').prop('checked', false);
});

// ---

enableTipInput()

$('form[name="checkout_address"]').on('click', '#tip-incr', function(e) {
  e.preventDefault()

  const mask = document.querySelector('#tip-input').inputmask
  mask.setValue(getValue(mask) + 1.00)

  updateTip()
})

$('#guest-checkout-signin').on('shown.bs.collapse', function () {
  const $password = $(this).find('input[type="password"]')
  $password.prop('required', true)
  setTimeout(() => $password.focus(), 100)
})

$('#guest-checkout-signin').on('hidden.bs.collapse', function () {
  $(this).find('input[type="password"]').prop('required', false)
})

$('#apply-coupon').on('click', function(e) {

  e.preventDefault()

  const $form = $('form[name="checkout_coupon"]')

  const data = {
    'checkout_coupon[promotionCoupon]': $('#coupon-code').val(),
    'checkout_coupon[_token]': $('#checkout_coupon__token').val(),
  }

  setLoading(true)

  $.ajax({
    url : $form.attr('action'),
    type: $form.attr('method'),
    data : data,
    success: function(html) {

      $('form[name="checkout_address"] table').replaceWith(
        $(html).find('form[name="checkout_address"] table')
      )

      enableTipInput()

      $('#coupon-code').val('')
      $('#promotion-coupon-collapse').collapse('hide')

      setLoading(false)
    }
  })
})

const nonprofitInput = document.getElementById('nonprofit_input');
const nonprofitCards = Array.from(document.getElementsByClassName('nonprofit-card'))
window.setNonprofit = function (elem) {
  nonprofitInput.value = elem.dataset.value;
  nonprofitCards.map(x => x.classList.remove('active'));
  elem.classList.add("active");
}

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
