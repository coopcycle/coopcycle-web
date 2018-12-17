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

export default function(form, options) {

  $('.btn-payment').attr('disabled', true)

  const stripe = Stripe(options.publishableKey)
  var elements = stripe.elements()

  var card = elements.create('card', { style, hidePostalCode: true })

  card.mount('#card-element')

  card.on('ready', function() {
    $('.btn-payment').attr('disabled', false)
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
    $('.btn-payment').attr('disabled', true)

    stripe.createToken(card).then(function(result) {
      if (result.error) {
        $('.btn-payment').removeClass('btn-payment__loading')
        $('.btn-payment').attr('disabled', false)
        var errorElement = document.getElementById('card-errors')
        errorElement.textContent = result.error.message
      } else {
        options.tokenElement.setAttribute('value', result.token.id)
        form.submit()
      }
    })
  })

}
