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
};

export default (form, options) => {

  const stripe = Stripe(options.publishableKey)
  var elements = stripe.elements()

  var card = elements.create('card', { style, hidePostalCode: true })

  card.mount('#card-element')

  card.addEventListener('change', function(event) {
    var displayError = document.getElementById('card-errors')
    if (event.error) {
      displayError.textContent = event.error.message
    } else {
      displayError.textContent = ''
    }
  })

  form.addEventListener('submit', function(event) {
    event.preventDefault();

    stripe.createToken(card).then(function(result) {
      if (result.error) {
        var errorElement = document.getElementById('card-errors')
        errorElement.textContent = result.error.message
      } else {
        options.tokenElement.setAttribute('value', result.token.id)
        form.submit()
      }
    })
  })

}
