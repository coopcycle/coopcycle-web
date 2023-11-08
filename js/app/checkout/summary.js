import Inputmask from 'inputmask'
import numbro from 'numbro'
import _ from 'lodash'
import React from 'react'
import { render } from 'react-dom'
import { getCurrencySymbol } from '../i18n'
import LoopeatModal from './LoopeatModal'

require('gasparesganga-jquery-loading-overlay')

import './summary.scss'

const {
  currency,
  currencyFormat,
  delimiters
} = numbro.languageData()

const space = currencyFormat.spaceSeparatedCurrency ? ' ' : ''

var im = new Inputmask("currency", {
    radixPoint: delimiters.decimal,
    suffix: currency.position === 'postfix' ? `${space}${getCurrencySymbol()}` : ''
})

const submitBtns = document.querySelectorAll(
  'form[name="checkout_address"] input[type="submit"],button[type="submit"]'
)
const mainSubmitBtn = _.find(Array.from(submitBtns), btn => !btn.hasAttribute('name'))

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

const updateTip = _.debounce(function() {

  const mask = document.querySelector('#tip-input').inputmask
  const newValue = mask.unmaskedvalue()

  var $form = $('form[name="checkout_tip"]')

  var data = {}
  data['checkout_tip[amount]'] = newValue
  data['checkout_tip[_token]'] = $('#checkout_tip__token').val()

  $('form[name="checkout_address"] table').LoadingOverlay('show', {
    image: false,
  })
  mainSubmitBtn.setAttribute('disabled', true)
  mainSubmitBtn.classList.add('disabled')

  $.ajax({
    url : $form.attr('action'),
    type: $form.attr('method'),
    data : data,
    success: function(html) {
      $('form[name="checkout_address"] table').replaceWith(
        $(html).find('form[name="checkout_address"] table')
      )

      enableTipInput()

      $('form[name="checkout_address"] table').LoadingOverlay('hide')
      mainSubmitBtn.removeAttribute('disabled')
      mainSubmitBtn.classList.remove('disabled')
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

  $('form[name="checkout_address"] table').LoadingOverlay('show', {
    image: false,
  })
  mainSubmitBtn.setAttribute('disabled', true)
  mainSubmitBtn.classList.add('disabled')

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

      $('form[name="checkout_address"] table').LoadingOverlay('hide')
      mainSubmitBtn.removeAttribute('disabled')
      mainSubmitBtn.classList.remove('disabled')
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
