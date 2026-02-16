import React, { useEffect, useState } from 'react'
import { createRoot } from 'react-dom/client'

import { Select } from 'antd';

function showPreview (wrapper) {
  const formPreviewEl = document.createElement('div')
  createRoot(formPreviewEl).render(<DeliveryFormPreview />)
  wrapper.appendChild(formPreviewEl)
}

const DeliveryFormPreview = () => {
  return (
    <section className="homepage-delivery">
      <div className="homepage-delivery-text">
        <h2 className="mt-0">Vous avez besoin dune livraison ?</h2>
        <p><strong>CoopCycle</strong> propose des services locaux et non polluants de livraison à vélo à la demande. Expédiez un colis ponctuellement ou de manière récurrente pour votre entreprise.</p>
      </div>
      <div className="homepage-delivery-form">
        <form className="form-horizontal">
          <div className="ssc">
            <div className="mb ssc-head-line w-100"></div>
            <div className="mb ssc-head-line w-100"></div>
          </div>
          <button type="button" className="btn btn-block btn-lg btn-primary">Suivant →</button>
        </form>
      </div>
    </section>
  )
}

export default class DeliveryForm {

  static get toolbox() {
    return {
      title: 'Delivery Form',
      // https://lucide.dev/icons/form
      icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-form-icon lucide-form"><path d="M4 14h6"/><path d="M4 2h10"/><rect x="4" y="18" width="16" height="4" rx="1"/><rect x="4" y="6" width="16" height="4" rx="1"/></svg>'
    };
  }

  constructor({ data, config }){
    this.config = config;
    this.form = data.form || null
  }

  render() {

    const wrapper = document.createElement('div');
    wrapper.style.marginBottom = '20px'

    const selectEl = document.createElement('div')
    selectEl.classList.add('mb-3')
    createRoot(selectEl).render(<Select
      defaultValue={this.form}
      style={{ width: 120 }}
      onChange={(value) => {
        this.form = value
        showPreview(wrapper)
      }}
      options={this.config.forms.map((f, i) => ({ value: f['@id'], label: `Form #${i + 1}` }))}
    />);
    wrapper.appendChild(selectEl)

    if (this.form) {
      showPreview(wrapper)
    }

    return wrapper;
  }

  save(blockContent) {
    return {
      form: this.form
    }
  }
}
