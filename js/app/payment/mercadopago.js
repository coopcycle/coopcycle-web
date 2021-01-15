import React, { useState } from 'react'
import { render, unmountComponentAtNode } from 'react-dom'
import { PaymentInputsWrapper, usePaymentInputs } from 'react-payment-inputs'
import images from 'react-payment-inputs/images'
import _ from 'lodash'

// @see https://www.mercadopago.com.mx/developers/es/guides/payments/api/receiving-payment-by-card/

function getInstallments(cardNumber, amount, setPaymentMethod, setInstallments) {

  if (cardNumber.length >= 6) {
      let bin = cardNumber.substring(0, 6)
      // Obtener método de pago de la tarjeta
      Mercadopago.getPaymentMethod({
          "bin": bin
      }, (status, response) => {
        if (status === 200) {
          setPaymentMethod(response[0].id)
          // Obtener cantidad de cuotas
          Mercadopago.getInstallments({
            "payment_method_id": response[0].id,
            "amount": (amount / 100),
          }, (status, response) => {
            if (status === 200) {
              setInstallments(response[0].payer_costs)
            } else {
                alert(`installments method info error: ${response}`);
            }
          })
        } else {
          // TODO Show error message
        }
      })
  }
}

const paymentMethodRef = React.createRef()
const installmentsRef = React.createRef()

const MercadoPagoForm = ({ amount, onChange }) => {

  const [ cardNumber, setCardNumber ] = useState('')
  const [ expiryDate, setExpiryDate ] = useState('')
  const [ paymentMethod, setPaymentMethod ] = useState('')
  const [ installments, setInstallments ] = useState([])

  const {
    wrapperProps,
    getCardImageProps,
    getCardNumberProps,
    getExpiryDateProps,
    getCVCProps,
  } = usePaymentInputs()
  const { error: formError } = wrapperProps
  const [ expiryDateMonth, expiryDateYear ] = expiryDate.split('/').map(i => i.trim())

  const cardNumberProps = getCardNumberProps({
    onChange: e => {
      // Obtener método de pago de la tarjeta
      // Obtener cantidad de cuotas
      getInstallments(e.target.value, amount, setPaymentMethod, setInstallments)
      setCardNumber(e.target.value)
    }
  })

  const expiryDateProps = getExpiryDateProps({
    onChange: e => setExpiryDate(e.target.value)
  })

  const cvcProps = getCVCProps()

  React.useEffect(() => {
    onChange(formError === undefined)
  }, [formError])

  return (
    <PaymentInputsWrapper { ...wrapperProps }>
      <input type="hidden" data-checkout="cardNumber" value={ cardNumber } />
      { /* Mercado Pago expects separate fields for month & year */ }
      <input type="hidden" data-checkout="cardExpirationMonth" value={ expiryDateMonth || '' } />
      <input type="hidden" data-checkout="cardExpirationYear" value={ expiryDateYear || '' } />

      <svg { ...getCardImageProps({ images }) } />
      { /* We need to remove the "name" attributes from the managed fields, for security */ }
      { /* @see https://github.com/medipass/react-payment-inputs/issues/47 */ }
      <input { ..._.omit(cardNumberProps, ['name']) } />
      <input { ..._.omit(expiryDateProps, ['name']) } />
      <input { ..._.omit(cvcProps, ['name']) } data-checkout="securityCode" />
      { /* The value of the fields below will be copied to the "real" form fields before creating a token */ }
      { installments.length > 0 && (
        <select ref={ installmentsRef }>
          { installments.map((installment, i) => (
            <option key={ `installment-${i}` }
              value={ installment.installments }>{ installment.recommended_message }</option>
          )) }
        </select>
      ) }
      <input type="hidden" ref={ paymentMethodRef } value={ paymentMethod || '' } />
    </PaymentInputsWrapper>
  );
}

export default ({ onChange }) => ({
  init(form) {
    this.form = form
    const { country, publishableKey } = this.config.gatewayConfig

    Mercadopago.setPublishableKey(publishableKey)

    if (country !== 'mx') {
      Mercadopago.getIdentificationTypes()
    }
  },
  mount(el) {
    this.el = el
    return new Promise((resolve) => {
      render(<MercadoPagoForm
        amount={ this.config.amount }
        onChange={onChange} />, el, resolve)
    })
  },
  unmount() {
    unmountComponentAtNode(this.el)
  },
  createToken() {

    // FIXME There must be a better way
    document.getElementById('checkout_payment_paymentMethod').value = paymentMethodRef.current.value
    document.getElementById('checkout_payment_installments').value = installmentsRef.current.value

    return new Promise((resolve, reject) => {
      Mercadopago.createToken(this.form, function(status, response) {
        if (status !== 200 && status !== 201) {
          // TODO Show error
          reject(new Error('The payment data is not valid'))
        } else {
          resolve(response.id)
        }
      })
    })
  }
})
