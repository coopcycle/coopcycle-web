import React, { useState } from 'react'
import { render, unmountComponentAtNode } from 'react-dom'
import { PaymentInputsWrapper, usePaymentInputs } from 'react-payment-inputs'
import images from 'react-payment-inputs/images'
import _ from 'lodash'
import { useTranslation } from 'react-i18next'

// @see https://www.mercadopago.com.mx/developers/es/guides/payments/api/receiving-payment-by-card/

function getInstallments(cardNumber, amount, setPaymentMethod, setInstallments) {

  const cardNumberClean = cardNumber.replace(/\s/g,'')

  if (cardNumberClean.length >= 6) {
      let bin = cardNumberClean.substring(0, 6)
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

  const { t } = useTranslation()

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
    <React.Fragment>
      <div className="form-group">
        <label className="control-label required">
          { t('PAYMENT_FORM_CARDHOLDER_NAME') }
        </label>
        <input type="text"
          required="required"
          data-checkout="cardholderName"
          className="form-control" />
      </div>
      <div className="form-group">
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
      </div>
    </React.Fragment>
  );
}

export default ({ onChange }) => ({
  init(form) {
    this.form = form
    const { country, restaurantPublicKey } = this.config.gatewayConfig

    if (!restaurantPublicKey) {
      throw new Error('Current restaurant has not configured the Public Key for Mercadopago')
    }

    /*
     * With the Public Key of the Marketplace owner (Coop) the payment is not working
     * so now we are going to test in production with the public key of the restaurant.
     */
    Mercadopago.setPublishableKey(restaurantPublicKey)

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
