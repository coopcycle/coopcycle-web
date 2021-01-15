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

// @see https://stripe.com/docs/payments/accept-a-payment-synchronously

function handleServerResponse(response, stripe) {

  return new Promise((resolve, reject) => {
    if (response.requires_action) {

      // Use Stripe.js to handle required card action
      stripe.handleCardAction(
        response.payment_intent_client_secret
      ).then(function(result) {
        if (result.error) {
          reject(result)
        } else {
          resolve(result.paymentIntent.id)
        }
      })

    } else {
      resolve(response.payment_intent)
    }
  })
}

export default {
  init() {

    let stripeOptions = {}

    if (this.config.gatewayConfig.account) {
      stripeOptions = {
        ...stripeOptions,
        stripeAccount: this.config.gatewayConfig.account,
      }
    }

    // @see https://stripe.com/docs/payments/payment-methods/connect#creating-paymentmethods-directly-on-the-connected-account
    this.stripe = Stripe(this.config.gatewayConfig.publishableKey, stripeOptions)

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

      this.stripe.createPaymentMethod({
        type: 'card',
        card: this.card,
        billing_details: {
          name: this.config.cardholderNameElement.value
        }
      }).then((createPaymentMethodResult) => {
        if (createPaymentMethodResult.error) {
          reject(new Error(createPaymentMethodResult.error.message))
        } else {
          axios.post(this.config.gatewayConfig.createPaymentIntentURL, {
            payment_method_id: createPaymentMethodResult.paymentMethod.id
          }).then((response) => {
            if (response.data.error) {
              reject(new Error(response.data.error.message))
            } else {
              handleServerResponse(response.data, this.stripe)
                .then(paymentIntentId => resolve(paymentIntentId))
                .catch(e => reject(new Error(e.error.message)))
            }
          }).catch(e => {
            // https://github.com/axios/axios#handling-errors
            if (e.response) {
              reject(new Error(e.response.data.error.message))
            } else {
              reject(new Error('An unexpected error occurred, please try again later'))
            }
          })
        }
      })
    })
  },
  // @see https://stripe.com/docs/payments/giropay/accept-a-payment#confirm-giropay-payment
  // https://stripe.com/docs/js/payment_intents/confirm_giropay_payment
  confirmGiropayPayment() {

    return new Promise((resolve, reject) => {

      axios.post(this.config.gatewayConfig.createGiropayPaymentIntentURL)
        .then(response => {
          this.stripe.confirmGiropayPayment(
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
