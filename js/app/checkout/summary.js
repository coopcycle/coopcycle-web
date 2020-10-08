import Inputmask from 'inputmask'
import numbro from 'numbro'
import _ from 'lodash'
import { getCurrencySymbol } from '../i18n'

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

const loopeatIframe = document.querySelector('#modal-loopeat iframe');
const wasChecked = $('#checkout_address_reusablePackagingEnabled').is(':checked');

let preventUncheck = false;

function submitForm() {
  $('#checkout_address_reusablePackagingEnabled').closest('form').submit();
}

function onMessage(e) {
  if (e.source === loopeatIframe.contentWindow) {
    var messageData = JSON.parse(e.data)
    if (messageData && messageData.loopeat) {
      if (messageData.loopeat.success) {
        preventUncheck = true;
        $('#modal-loopeat').modal('hide');

        $('#checkout_address_reusablePackagingEnabled').prop('checked', true);
        submitForm();
      } else {
        $('#modal-loopeat').modal('hide');
      }
    }
  }
}
window.addEventListener('message', onMessage, true);

$('#modal-loopeat').on('shown.bs.modal', function() {
  preventUncheck = false;
});
$('#modal-loopeat').on('hidden.bs.modal', function() {
  if (!preventUncheck) {
    $('#checkout_address_reusablePackagingEnabled').prop('checked', false);
    if (wasChecked) submitForm();
  }
});

$('#loopeat-add-credit').on('click', function(e) {
  e.preventDefault();

  var required = $('#checkout_address_reusablePackagingEnabled').data('loopeatRequired');
  var iframeUrl = $('#checkout_address_reusablePackagingEnabled').data('loopeatAuthorizeUrl');
  var oAuthFlow = $('#checkout_address_reusablePackagingEnabled').data('loopeatOauthFlow');

  if (iframeUrl) {
    if (oAuthFlow === 'iframe') {
      $('#modal-loopeat iframe').attr('src', iframeUrl + '&loopeats_required='+required);
      $('#modal-loopeat').modal('show');
    } else {
      $('#modal-loopeat-redirect-warning [data-continue]')
        .off('click')
        .on('click', () => window.location.href = iframeUrl + '&loopeats_required='+required)
      $('#modal-loopeat-redirect-warning').modal('show');
    }
  }
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
  var isLoopeat = $(this).data('loopeat') === true;
  var iframeUrl = $(this).data('loopeatAuthorizeUrl');
  var oAuthFlow = $(this).data('loopeatOauthFlow');
  var hasCredentials = $(this).data('loopeatCredentials') === true;
  if (!hasCredentials && isChecked && isLoopeat && iframeUrl) {
    if (oAuthFlow === 'iframe') {
      $('#modal-loopeat iframe').attr('src', iframeUrl);
      $('#modal-loopeat').modal('show');
    } else {
      $('#modal-loopeat-redirect-warning [data-continue]')
        .off('click')
        .on('click', () => window.location.href = iframeUrl)
      $('#modal-loopeat-redirect-warning').modal('show');
    }
  } else {
    submitForm();
  }
});

$('#modal-loopeat-redirect-warning').on('hidden.bs.modal', function() {
  $('#modal-loopeat-redirect-warning [data-continue]').off('click');
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
