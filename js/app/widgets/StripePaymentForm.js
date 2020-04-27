import React, { useState } from 'react'
import { render } from 'react-dom'
import _ from 'lodash'
import classNames from 'classnames'

import mastercard from 'payment-icons/min/flat/mastercard.svg'
import visa from 'payment-icons/min/flat/visa.svg'
import giropay from '../../../assets/svg/giropay.svg'

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

  return (
    <div style={ methodPickerStyles }>
      <button type="button" className={ classNames({ ...methodPickerBtnClassNames, active: method === 'card' }) }
        onClick={ () => { setMethod('card'); onSelect('card') } }>
        <img src={ visa } height="45" className="mr-2" />
        <img src={ mastercard } height="45" />
      </button>
      { _.includes(methods, 'giropay') && (
        <button type="button"  className={ classNames({ ...methodPickerBtnClassNames, active: method === 'giropay' }) }
          onClick={ () => { setMethod('giropay'); onSelect('giropay') } }>
          <img src={ giropay } height="45" />
        </button>
      )}
    </div>
  )
}

export default function(form, options) {

  const submitButton = form.querySelector('input[type="submit"],button[type="submit"]')

  const methods = Array
    .from(form.querySelectorAll('input[name="checkout_payment[method]"]'))
    .map((el) => el.value)

  disableBtn(submitButton)

  const stripe = Stripe(options.publishableKey)
  const elements = stripe.elements()

  const card = elements.create('card', { style, hidePostalCode: true })

  card.addEventListener('change', function(event) {
    const displayError = document.getElementById('card-errors')
    if (event.error) {
      displayError.textContent = event.error.message
    } else {
      displayError.textContent = ''
    }
  })

  card.on('ready', function() {
    enableBtn(submitButton)
  })

  form.addEventListener('submit', function(event) {

    if (methods.length > 1 && form.querySelector('input[name="checkout_payment[method]"]:checked').value !== 'card') {
      return
    }

    event.preventDefault()

    $('.btn-payment').addClass('btn-payment__loading')
    disableBtn(submitButton)

    stripe.createToken(card).then(function(result) {
      if (result.error) {
        $('.btn-payment').removeClass('btn-payment__loading')
        enableBtn(submitButton)
        var errorElement = document.getElementById('card-errors')
        errorElement.textContent = result.error.message
      } else {
        options.tokenElement.setAttribute('value', result.token.id)
        form.submit()
      }
    })
  })

  const onSelect = value => {
    form.querySelector(`input[name="checkout_payment[method]"][value="${value}"]`).checked = true
    if (value === 'card') {
      card.mount('#card-element')
      document.getElementById('payment-redirect-help').classList.add('hidden')
    } else {
      card.unmount()
      document.getElementById('card-errors').textContent = ''
      document.getElementById('payment-redirect-help').classList.remove('hidden')
      enableBtn(submitButton)
    }
  }

  if (methods.length > 1) {

    // Replace radio buttons

    document
      .querySelectorAll('#checkout_payment_method .radio')
      .forEach(el => el.classList.add('hidden'))

    const el = document.createElement('div')
    document.querySelector('#checkout_payment_method').appendChild(el)

    render(
      <PaymentMethodPicker methods={ methods } onSelect={ onSelect } />,
      el
    )

  } else {
    card.mount('#card-element')
  }
}
