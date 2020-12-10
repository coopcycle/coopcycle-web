import axios from 'axios'

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
  },
  // @see https://stripe.com/docs/payments/giropay/accept-a-payment#confirm-giropay-payment
  // https://stripe.com/docs/js/payment_intents/confirm_giropay_payment
  confirmGiropayPayment() {

    return new Promise((resolve, reject) => {

      let stripeOptions = {}

      if (this.config.gatewayConfig.account) {
        stripeOptions = {
          ...stripeOptions,
          stripeAccount: this.config.gatewayConfig.account,
        }
      }

      // @see https://stripe.com/docs/payments/payment-methods/connect#creating-paymentmethods-directly-on-the-connected-account
      const stripe = Stripe(this.config.gatewayConfig.publishableKey, stripeOptions)

      axios.post(this.config.gatewayConfig.createGiropayPaymentIntentURL)
        .then(response => {
          stripe.confirmGiropayPayment(
            response.data.payment_intent_client_secret,
            {
              payment_method: {
                billing_details: {
                  name: this.config.cardholderNameElement.value
                }
              },
              return_url: response.data.return_url,
            }
          ).then(function(result) {
            if (result.error) {
              reject(new Error(result.error.message))
            } else {
              resolve()
            }
          })
        })
    })
  }
}
