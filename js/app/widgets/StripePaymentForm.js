var style = {
  base: {
    color: '#32325d',
    lineHeight: '18px',
    fontFamily: '"Helvetica Neue", Helvetica, sans-serif',
    fontSmoothing: 'antialiased',
    fontSize: '16px',
    '::placeholder': {
      color: '#aab7c4'
    }
  },
  invalid: {
    color: '#fa755a',
    iconColor: '#fa755a'
  }
}

function disableBtn(btn) {
  btn.setAttribute('disabled', '')
  btn.disabled = true
}

function enableBtn(btn) {
  btn.disabled = false
  btn.removeAttribute('disabled')
}

export default function(form, options) {

  const submitButton = form.querySelector('input[type="submit"],button[type="submit"]')

  disableBtn(submitButton)

  const stripe = Stripe(options.publishableKey)
  var elements = stripe.elements()

  var card = elements.create('card', { style, hidePostalCode: true })

  card.mount('#card-element')

  card.on('ready', function() {
    enableBtn(submitButton)
  })

  card.addEventListener('change', function(event) {
    var displayError = document.getElementById('card-errors')
    if (event.error) {
      displayError.textContent = event.error.message
    } else {
      displayError.textContent = ''
    }
  })

  form.addEventListener('submit', function(event) {
    event.preventDefault()
    $('.btn-payment').addClass('btn-payment__loading')
    disableBtn(submitButton)

    stripe.createToken(card).then(function(result) {
      if (result.error) {
        $('.btn-payment').removeClass('btn-payment__loading')
        enableBtn(submitButton)
        var errorElement = document.getElementById('card-errors')
        errorElement.textContent = result.error.message
      } else {
        options.tokenElement.setAttribute('value', result.token.id)
        form.submit()
      }
    })
  })

}
