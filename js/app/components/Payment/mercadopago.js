import React from 'react'
import { render } from 'react-dom'

/**
 * see https://www.mercadopago.com.ar/developers/en/docs/checkout-bricks/card-payment-brick/introduction
 */
export default {
  async init() {
    const { publishableKey } = this.config.gatewayConfig

    if (!publishableKey) {
      throw new Error('You need a Public Key for Mercadopago')
    }

    this.mpInstance = new window.MercadoPago(publishableKey);
  },
  async mount(el) {
    return new Promise((resolve) => {
      render(<div id="cardPaymentBrick_container"></div>, el, async () => {
        await this.createBrickContainer()
        resolve()
      })
    })
  },
  async createBrickContainer() {
    const bricksBuilder = this.mpInstance.bricks()

    const settings = {
      initialization: {
        amount: (this.config.amount / 100),
      },
      customization: {
        visual: {
          hidePaymentButton: true,
          style: { theme: 'bootstrap' }
        }
      },
      callbacks: {
        onReady: () => document.getElementById('cardPaymentBrick_container').scrollIntoView(),
        onError: (error) => {
          console.error(JSON.stringify(error))
          document.getElementById('card-errors').textContent = error.message
        },
      },
    }

    window.cardPaymentBrickController = await bricksBuilder.create('cardPayment', 'cardPaymentBrick_container', settings)
  },
  unmount() {
    window.cardPaymentBrickController.unmount()
  },
  async createToken() {
    const data = await window.cardPaymentBrickController.getFormData()

    if (!data) {
      return null
    }

    document.getElementById('checkout_payment_paymentMethod').value = data.payment_method_id
    document.getElementById('checkout_payment_installments').value = data.installments ? data.installments : 1

    const cardTokenData = {
      cardholderName: document.getElementById('cardPaymentBrick_container').querySelector('input[name="HOLDER_NAME"]').value,
      identificationType: data.payer.identification.type,
      identificationNumber: data.payer.identification.number,
    }

    try {
      const token = await this.mpInstance.fields.createCardToken(cardTokenData);
      return token.id
    } catch(err) {
      if (err.message) {
        throw new Error(err.message)
      } else {
        throw new Error('An unexpected error occurred, please try again later')
      }
    }
  }
}
