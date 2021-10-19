import React, { useState, useEffect } from 'react'
import { render } from 'react-dom'
import _ from 'lodash'
import classNames from 'classnames'
import axios from 'axios'

import mastercard from 'payment-icons/min/flat/mastercard.svg'
import visa from 'payment-icons/min/flat/visa.svg'
import giropay from '../../../assets/svg/giropay.svg'
import edenredLogo from '../../../assets/svg/Edenred_Logo.svg'
import cashLogo from '../../../assets/svg/dollar-bill-svgrepo-com.svg'

import stripe from '../payment/stripe'
import mercadopago from '../payment/mercadopago'
import { Disclaimer } from '../payment/cashOnDelivery'

function disableBtn(btn) {
  btn.setAttribute('disabled', '')
  btn.disabled = true
}

function enableBtn(btn) {
  btn.disabled = false
  btn.removeAttribute('disabled')
}

const methodPickerStyles = {
  display: 'flex',
  flexDirection: 'row',
  alignItems: 'center',
  justifyContent: 'space-between',
}

const methodPickerBtnClassNames = {
  'btn': true,
  'btn-default': true,
  'p-2': true
}

const PaymentMethodPicker = ({ methods, onSelect }) => {

  const [ method, setMethod ] = useState('')

  useEffect(() => {
    if (method) {
      onSelect(method)
    }
  }, [ method ])

  return (
    <div style={ methodPickerStyles }>
      { _.map(methods, m => {

        switch (m.type) {

        case 'card':
          return (
            <button key={ m.type } type="button" className={ classNames({ ...methodPickerBtnClassNames, active: method === 'card' }) }
              onClick={ () => setMethod('card') }>
              <img src={ visa } height="45" className="mr-2" />
              <img src={ mastercard } height="45" />
            </button>
          )

        case 'giropay':

          return (
            <button key={ m.type } type="button" className={ classNames({ ...methodPickerBtnClassNames, active: method === 'giropay' }) }
              onClick={ () => setMethod('giropay') }>
              <img src={ giropay } height="45" />
            </button>
          )

        case 'edenred':
        case 'edenred+card':

          return (
            <button key={ m.type } type="button" className={ classNames({ ...methodPickerBtnClassNames, active: method === m.type }) }
              onClick={ () => {

                if (!m.data.edenredIsConnected) {
                  window.location.href = m.data.edenredAuthorizeUrl
                  return
                }

                setMethod(m.type)
              }}>
              <img src={ edenredLogo } height="45" />
            </button>
          )

        case 'cash_on_delivery':

          return (
            <button key={ m.type } type="button" className={ classNames({ ...methodPickerBtnClassNames, active: method === m.type }) }
              onClick={ () => setMethod('cash_on_delivery') }>
              <img src={ cashLogo } height="45" />
            </button>
          )

        }
      }) }
    </div>
  )
}

class CreditCard {
 constructor(config) {
   this.config = config;
 }
}

const containsMethod = (methods, method) => !!_.find(methods, m => m.type === method)

const handleCardPayment = (cc, options, form, submitButton) => {
  cc.createToken()
    .then(token => {
      options.tokenElement.setAttribute('value', token)
      form.submit()
    })
    .catch(e => {
      $('.btn-payment').removeClass('btn-payment__loading')
      enableBtn(submitButton)
      document.getElementById('card-errors').textContent = e.message
    })
}

export default function(form, options) {

  const submitButton = form.querySelector('input[type="submit"],button[type="submit"]')

  const toggleButton = isValidForm => isValidForm ? enableBtn(submitButton) : disableBtn(submitButton)

  const methods = Array
    .from(form.querySelectorAll('input[name="checkout_payment[method]"]'))
    .map((el) => ({
      type: el.value,
      data: { ...el.dataset }
    }))

  // Hotfix for embedded form
  if (form.dataset.paymentMethodPicker && JSON.parse(form.dataset.paymentMethodPicker) === false) {
    methods.push({
      type: 'card',
      data: {},
    })
  }

  disableBtn(submitButton)

  let cc

  if (containsMethod(methods, 'card')) {

    const gatewayForCard = options.card || 'stripe'
    const gatewayConfig = options.gatewayConfigs ? options.gatewayConfigs[gatewayForCard] : { publishableKey: options.publishableKey }

    switch (gatewayForCard) {
      case 'mercadopago':
        Object.assign(CreditCard.prototype, mercadopago({ onChange: toggleButton }))
        break
      case 'stripe':
      default:
        Object.assign(CreditCard.prototype, stripe)
    }

    cc = new CreditCard({
      gatewayConfig,
      amount: options.amount,
      onChange: (event) => {
        if (event.error) {
          document.getElementById('card-errors').textContent = event.error.message
        } else {
          event.complete && enableBtn(submitButton)
          document.getElementById('card-errors').textContent = ''
        }
      },
    })

    cc.init(form)

  }

  form.addEventListener('submit', function(event) {

    event.preventDefault()

    $('.btn-payment').addClass('btn-payment__loading')
    disableBtn(submitButton)

    if (methods.length === 1 && containsMethod(methods, 'card')) {
      handleCardPayment(cc, options, form, submitButton)
    } else {

      const selectedMethod =
        form.querySelector('input[name="checkout_payment[method]"]:checked').value

      switch (selectedMethod) {
        case 'giropay':
          cc.confirmGiropayPayment()
            .catch(e => {
              $('.btn-payment').removeClass('btn-payment__loading')
              enableBtn(submitButton)
              document.getElementById('card-errors').textContent = e.message
            })
          break
        case 'edenred':
          // It means the whole amount can be paid with Edenred (ex. click & collect)
          form.submit()
          break
        case 'cash_on_delivery':
          form.submit()
          break
        case 'edenred+card':
        case 'card':
          handleCardPayment(cc, options, form, submitButton)
          break
      }
    }

  })

  const onSelect = value => {
    form.querySelector(`input[name="checkout_payment[method]"][value="${value}"]`).checked = true
    axios
      .post(options.selectPaymentMethodURL, { method: value })
      .then(response => {
        switch (value) {
          case 'card':
          case 'giropay':
          case 'edenred+card':
            cc.mount(document.getElementById('card-element'), value, response.data).then(() => {
              document.getElementById('card-element').scrollIntoView()
              enableBtn(submitButton)
            })
            break
          case 'edenred':
            // TODO
            // Here no need to enter credit card details or what
            // Maybe, add a confirmation step?
            enableBtn(submitButton)
            break
          case 'cash_on_delivery':
            enableBtn(submitButton)

            const el = document.createElement('div')
            document.querySelector('#checkout_payment_method').appendChild(el)

            render(<Disclaimer />, el)

            break
          default:
            cc && cc.unmount()
            document.getElementById('card-errors').textContent = ''
            enableBtn(submitButton)
        }
      })
  }

  // Replace radio buttons

  document
    .querySelectorAll('#checkout_payment_method .radio')
    .forEach(el => el.classList.add('d-none'))

  if (methods.length === 1 && containsMethod(methods, 'card')) {
    cc.mount(document.getElementById('card-element')).then(() => enableBtn(submitButton))
  } else {

    const el = document.createElement('div')
    document.querySelector('#checkout_payment_method').appendChild(el)

    render(
      <PaymentMethodPicker methods={ methods } onSelect={ onSelect } />,
      el
    )
  }
}
