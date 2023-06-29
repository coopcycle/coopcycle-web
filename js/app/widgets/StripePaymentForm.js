import React, { useState, useEffect } from 'react'
import { render } from 'react-dom'
import _ from 'lodash'
import classNames from 'classnames'
import axios from 'axios'

import PaymentMethodIcon from '../components/PaymentMethodIcon'
import stripe from '../payment/stripe'
import mercadopago from '../payment/mercadopago'
import { Disclaimer } from '../payment/cashOnDelivery'

import { useTranslation } from 'react-i18next'

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
  marginTop: '8px'
}

const methodPickerBtnClassNames = {
  'btn': true,
  'btn-default': true,
  'p-2': true
}

const methodStyles = {
  display: 'flex',
  flexDirection: 'column',
  alignItems: 'start',
  justifyContent: 'space-between',
}

const PaymentMethodPicker = ({ methods, onSelect }) => {

  const { t } = useTranslation()

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
            <div style={ methodStyles }>
              <label>{ t('PM_CREDIT_OR_DEBIT_CARD') }</label>
              <button key={ m.type } type="button" className={ classNames({ ...methodPickerBtnClassNames, active: method === 'card' }) }
                onClick={ () => setMethod('card') }>
                <PaymentMethodIcon code={ m.type } height="45" />
              </button>
            </div>
          )

        case 'giropay':

          return (
            <div style={ methodStyles }>
              <label>{ t('PM_GIROPAY') }</label>
              <button key={ m.type } type="button" className={ classNames({ ...methodPickerBtnClassNames, active: method === 'giropay' }) }
                onClick={ () => setMethod('giropay') }>
                <PaymentMethodIcon code={ m.type } height="45" />
              </button>
            </div>
          )

        case 'edenred':
        case 'edenred+card':

          return (
            <div style={ methodStyles }>
              <label>{ t('PM_EDENRED') }</label>
              <button key={ m.type } type="button" className={ classNames({ ...methodPickerBtnClassNames, active: method === m.type }) }
                onClick={ () => {

                  if (!m.data.edenredIsConnected) {
                    window.location.href = m.data.edenredAuthorizeUrl
                    return
                  }

                  setMethod(m.type)
                }}>
                <PaymentMethodIcon code={ m.type } height="45" />
              </button>
            </div>
          )

        case 'cash_on_delivery':

          return (
            <div style={ methodStyles }>
              <label>{ t('PM_CASH') }</label>
              <button key={ m.type } type="button" className={ classNames({ ...methodPickerBtnClassNames, active: method === m.type }) }
                onClick={ () => setMethod('cash_on_delivery') }>
                <PaymentMethodIcon code={ m.type } height="45" />
              </button>
              </div>
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

const handleCardPayment = (cc, options, form, submitButton, savedPaymentMethodId = null) => {
  cc.createToken(savedPaymentMethodId)
    .then(token => {
      if (token) {
        options.tokenElement.setAttribute('value', token)
        form.submit()
      } else {
        $('.btn-payment').removeClass('btn-payment__loading')
        enableBtn(submitButton)
      }
    })
    .catch(e => {
      $('.btn-payment').removeClass('btn-payment__loading')
      enableBtn(submitButton)
      document.getElementById('card-errors').textContent = e.message
    })
}

export default function(form, options) {

  const submitButton = form.querySelector('input[type="submit"],button[type="submit"]')

  const methods = Array
    .from(form.querySelectorAll('input[name="checkout_payment[method]"]'))
    .map((el) => ({
      type: el.value,
      data: { ...el.dataset }
    }))

  disableBtn(submitButton)

  let cc

  if (containsMethod(methods, 'card')) {

    const gatewayForCard = options.card || 'stripe'
    const gatewayConfig = options.gatewayConfigs ? options.gatewayConfigs[gatewayForCard] : { publishableKey: options.publishableKey }

    switch (gatewayForCard) {
      case 'mercadopago':
        Object.assign(CreditCard.prototype, mercadopago)
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
      onSavedCreditCardSelected: (card) => {
        if (!card) {
          // used to blanck field when the card form is enabled
          options.savedPaymentMethodElement.removeAttribute('value')
        } else {
          options.savedPaymentMethodElement.setAttribute('value', card.id)
        }

      }
    })

    cc.init(form)

  }

  form.addEventListener('submit', function(event) {

    event.preventDefault()

    $('.btn-payment').addClass('btn-payment__loading')
    disableBtn(submitButton)

    let savedPaymentMethod = null
    if (options.savedPaymentMethodElement) {
      savedPaymentMethod = options.savedPaymentMethodElement.getAttribute('value')
    }

    if (methods.length === 1 && containsMethod(methods, 'card')) {
      handleCardPayment(cc, options, form, submitButton, savedPaymentMethod)
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
          handleCardPayment(cc, options, form, submitButton, savedPaymentMethod)
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
            const cashDisclaimer = document.getElementById('cash_on_delivery_disclaimer')
            if (cashDisclaimer) {
              // remove disclaimer for cash method if it was previously selected
              cashDisclaimer.remove()
            }

            cc.mount(document.getElementById('card-element'), value, response.data, options).then(() => {
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
            if (document.getElementById('card-element').children.length) {
              // remove cc form if it was previously mounted
              cc && cc.unmount()
            }

            enableBtn(submitButton)

            const disclaimer = document.getElementById('cash_on_delivery_disclaimer')

            if (!disclaimer) {
              // without this condition the disclaimer is rendered every time cash is selected
              const el = document.createElement('div')
              document.querySelector('#checkout_payment_method').appendChild(el)
              render(<Disclaimer />, el)
            }

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
    cc.mount(document.getElementById('card-element'), null, null, options).then(() => enableBtn(submitButton))
  } else {

    const el = document.createElement('div')
    document.querySelector('#checkout_payment_method').appendChild(el)

    render(
      <PaymentMethodPicker methods={ methods } onSelect={ onSelect } />,
      el
    )
  }
}
