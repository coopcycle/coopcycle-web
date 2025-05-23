import React from 'react'
import { createRoot } from 'react-dom/client'

const BrickContainer = ({ callback }) => <div ref={ callback } id="cardPaymentBrick_container"></div>

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
      createRoot(el).render(<BrickContainer callback={ async () => {
        await this.createBrickContainer()
        resolve()
      }} />)
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
    document.getElementById('checkout_payment_installments').value  = data.installments ? data.installments : 1
    document.getElementById('checkout_payment_issuer').value        = data.issuer_id
    document.getElementById('checkout_payment_payerEmail').value    = data.payer.email

    return data.token
  }
}
