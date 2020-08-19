const style = {
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

export default {
  init() {
    this.stripe = Stripe(this.config.gatewayConfig.publishableKey)

    const elements = this.stripe.elements()

    this.card = elements.create('card', { style, hidePostalCode: true })
    this.card.addEventListener('change', this.config.onChange)
  },
  mount(el) {
    return new Promise((resolve) => {
      this.card.off('ready')
      this.card.on('ready', resolve)
      this.card.mount(el)
    })
  },
  unmount() {
    this.card.unmount()
  },
  createToken() {
    return new Promise((resolve, reject) => {
      this.stripe.createToken(this.card).then(function(result) {
        if (result.error) {
          reject(new Error(result.error.message))
        } else {
          resolve(result.token.id)
        }
      })
    })
  }
}
