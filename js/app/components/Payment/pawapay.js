import React from 'react'
import { createRoot } from 'react-dom/client'

import './pawapay.scss'

import pawapayLogo from './pawapay.svg'

const Pawapay = () => {
  return (
    <div className="pawapay p-3 d-flex align-items-center">
      <img src={pawapayLogo} height="24" className="mr-2" />
      <span>Pay with our partner <strong>pawaPay</strong>. Click on the button below to proceed.</span>
    </div>
  )
}

export default {
  async init() {
  },
  async mount(el, method, options, formOptions) {
    this.redirectUrl = options.pawapay.pawapay_payment_page_url;

    createRoot(el).render(<Pawapay />)

    return new Promise((resolve) => {
      resolve()
    })
  },
  unmount() {
  },
  async createToken() {
    window.location.href = this.redirectUrl
  }
}
