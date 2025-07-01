import React, { StrictMode } from 'react'
import { createRoot } from 'react-dom/client'
import axios from 'axios'
import { PriceCalculation } from './PriceCalculation'

const baseURL = location.protocol + '//' + location.host

// @see https://gist.github.com/anvk/5602ec398e4fdc521e2bf9940fd90f84


function createPricingPromise(delivery, token, $container) {
  return new Promise((resolve) => {
    axios({
      method: 'post',
      url: baseURL + '/api/retail_prices/calculate',
      data: delivery,
      headers: {
        'Accept': 'application/ld+json',
        'Content-Type': 'application/ld+json',
        Authorization: `Bearer ${token}`
      }
    })
      .then(response => resolve({ success: true, data: response.data }))
      .catch(e => {
        let message = ''

        if (e.response && e.response.status === 400) {
          if (Object.prototype.hasOwnProperty.call(e.response.data, '@type') && e.response.data['@type'] === 'hydra:Error') {
            $container.addClass('delivery-price--error')
            message = e.response.data['hydra:description']
          }
        }

        resolve({ success: false, data: e.response.data, message })
      })
  })
}

class PricePreview {
  constructor() {
    this.token = null
  }
  getToken() {
    if (this.token) {
      return Promise.resolve(this.token)
    } else {
      return  $.getJSON(window.Routing.generate('profile_jwt'))
        .then(result => {
          const token = result.jwt
          this.token = token
          return token
        })
    }
  }
  update(delivery) {

    const $container = $('#delivery_price').closest('.delivery-price')

    $container.removeClass('delivery-price--error')
    $container.addClass('delivery-price--loading')
    $('#delivery_price_error').text('')
    $('#delivery_price')
      .find('[data-tax]')
      .text((0).formatMoney())

    return this.getToken().then((token) => {
      return createPricingPromise(delivery, token, $container)
    })
   .then(priceResult => {
     const { data } = priceResult

     if (priceResult.success) {

       const taxExcluded = data.amount - data.tax.amount

       $('#delivery_price')
         .find('[data-tax="included"]')
         .text((data.amount / 100).formatMoney())
       $('#delivery_price')
         .find('[data-tax="excluded"]')
         .text((taxExcluded / 100).formatMoney())

     } else {
       $('#delivery_price_error').text(priceResult.message)
     }

     $('#pricing-rules-debug').each(function (index, item) {
       const root = createRoot(item)
       root.render(
         <StrictMode>
           <PriceCalculation
             calculation={data.calculation}
             order={data.order}
             itemsTotal={data.amount} />
         </StrictMode>,
       )
     })

     $container.removeClass('delivery-price--loading')
   })
  }
}

export default function() {
  return new PricePreview()
}
