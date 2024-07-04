import React, { StrictMode } from 'react'
import { render } from 'react-dom'
import { createRoot } from 'react-dom/client'
import _ from 'lodash'
import axios from 'axios'

import stripe from './stripe'
import mercadopago from './mercadopago'
import { Disclaimer } from './cashOnDelivery'

import { disableBtn, enableBtn } from '../../widgets/button'
import PaymentMethodPicker from './PaymentMethodPicker'
import {
  selectOrderNodeId,
  selectShippingTimeRange,
} from '../../entities/order/reduxSlice'
import { selectPersistedTimeRange } from '../order/timeRange/reduxSlice'
import { checkTimeRange } from '../../utils/order/helpers'
import { apiSlice } from '../../api/slice'

class CreditCard {
  constructor(config) {
    this.config = config
  }
}

const containsMethod = (methods, method) => !!_.find(methods, m => m.type === method)

export default function(form, options) {

  const submitButton = form.querySelector('input[type="submit"],button[type="submit"]')

  const orderErrorContainerEl = document.getElementById('order-error-container')
  const orderErrorContainerRoot = orderErrorContainerEl
    ? createRoot(orderErrorContainerEl)
    : null

  function setLoading(isLoading) {
    if (isLoading) {
      $('.btn-payment').addClass('btn--loading')
      disableBtn(submitButton)
    } else {
      $('.btn-payment').removeClass('btn--loading')
      enableBtn(submitButton)
    }
  }

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

  const handleCardPayment = (savedPaymentMethodId = null) => {
    cc.createToken(savedPaymentMethodId)
      .then(token => {
        if (token) {
          options.tokenElement.setAttribute('value', token)
          form.submit()
        } else {
          setLoading(false)
        }
      })
      .catch(e => {
        setLoading(false)
        document.getElementById('card-errors').textContent = e.message
      })
  }

  const handlePayment = () => {
    let savedPaymentMethod = null
    if (options.savedPaymentMethodElement) {
      savedPaymentMethod = options.savedPaymentMethodElement.getAttribute('value')
    }

    if (methods.length === 1 && containsMethod(methods, 'card')) {
      handleCardPayment(savedPaymentMethod)
    } else {

      const selectedMethod =
        form.querySelector('input[name="checkout_payment[method]"]:checked').value

      switch (selectedMethod) {
        case 'giropay':
          cc.confirmGiropayPayment()
            .catch(e => {
              setLoading(false)
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
          handleCardPayment(savedPaymentMethod)
          break
      }
    }
  }

  form.addEventListener('submit', async function (event) {
    event.preventDefault()

    setLoading(true)

    //FIXME: only /order/payment route is tested to provide redux store; add to other routes when needed
    const store = window._rootStore
    if (store) {
      const orderNodeId = selectOrderNodeId(store.getState())

      let violations = null
      try {
        const { error } = await store.dispatch(
          apiSlice.endpoints.getOrderValidate.initiate(orderNodeId, {
            forceRefetch: true,
          }),
        )
        violations = error?.data?.violations
      } catch (error) {
        // ignore the request error and continue without the validation
        setLoading(false)
      }

      if (orderErrorContainerRoot && violations) {
        setLoading(false)
        orderErrorContainerRoot.render(
          <StrictMode>
            <div className="alert alert-danger">
              {violations.map((violation, index) => (
                <p key={index}>{violation.message}</p>
              ))}
            </div>
          </StrictMode>,
        )
        return
      }

      const shippingTimeRange = selectShippingTimeRange(store.getState())
      const persistedTimeRange = selectPersistedTimeRange(store.getState())

      // if the customer has already selected the time range, it will be checked on the server side
      if (!shippingTimeRange && persistedTimeRange) {
        try {
          await checkTimeRange(
            persistedTimeRange,
            store.getState,
            store.dispatch,
          )
        } catch (error) {
          setLoading(false)
          return
        }
      }
    }

    handlePayment()
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
