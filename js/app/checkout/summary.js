import Inputmask from 'inputmask'
import numbro from 'numbro'
import _ from 'lodash'
import { getCurrencySymbol } from '../i18n'

require('gasparesganga-jquery-loading-overlay')

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

  var $form = $('form[name="checkout_address"]')

  var data = {}
  data['checkout_address[tipAmount]'] = newValue
  data['checkout_address[addTip]'] = ''
  data['checkout_address[_token]'] = $('#checkout_address__token').val()

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

// ---

enableTipInput()

$('form[name="checkout_address"]').on('click', '#tip-incr', function(e) {
  e.preventDefault()

  const mask = document.querySelector('#tip-input').inputmask
  mask.setValue(getValue(mask) + 1.00)

  updateTip()
})
