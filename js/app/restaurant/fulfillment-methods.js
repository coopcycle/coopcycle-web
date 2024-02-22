import React from 'react'
import { render } from 'react-dom'
import OpeningHoursInput from '../widgets/OpeningHoursInput'
import DeliveryZonePicker from '../components/DeliveryZonePicker'
import OrdersRateLimiter from "../components/OrdersRateLimiter";

$(function() {

  const openingHoursInputs = new Map()

  document.querySelectorAll('[data-widget="opening-hours"]').forEach((el) => {

    const ohi = new OpeningHoursInput(el, {
      locale: $('html').attr('lang'),
      rowsWithErrors: JSON.parse(el.dataset.errors),
      behavior: el.dataset.behavior,
      withBehavior: true,
      disabled: JSON.parse(el.dataset.disabled),
      onChangeBehavior: behavior => {
        const input = document
          .querySelector(el.dataset.behaviorSelector)
          .querySelector(`input[value="${behavior}"]`)
        if (input) {
          input.checked = true
        }
      }
    })

    openingHoursInputs.set(el.dataset.method, ohi)
  })

  document.querySelectorAll('[data-enable-fulfillment-method]').forEach(checkbox => {

    const enableFulfillmentMethod = checkbox.dataset.enableFulfillmentMethod

    checkbox.addEventListener('change', e => {
      if (openingHoursInputs.has(enableFulfillmentMethod)) {
        const widget = openingHoursInputs.get(enableFulfillmentMethod)
        if (e.target.checked) {
          widget.enable()
        } else {
          widget.disable()
        }
      }
    })
  })

  document.querySelectorAll('[data-widget="delivery-perimeter-expression"]').forEach(el => {

    const input = el.querySelector('input[type="hidden"]')

    if (input) {

      const container = document.createElement('div')

      render(
        <DeliveryZonePicker
          zones={ JSON.parse(el.dataset.zones) }
          expression={ el.dataset.defaultValue }
          onExprChange={ expr => $(input).val(expr) }
        />, container)

      el.appendChild(container)
    }
  })

  // Orders Rate Limiter component scope
  {
    const widget = document.querySelector('[data-widget="orders-rate-limiter"]')

    if (widget) {
      const container = document.createElement('div')
      const input = widget.querySelector('input[type="hidden"]')

      render(
        <OrdersRateLimiter
          defaultValue={widget.dataset.defaultValue}
          onChange={v => input.value = v}
        />,
        container
      )

      widget.appendChild(container)
    }
  }

})
