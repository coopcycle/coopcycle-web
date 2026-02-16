import React, { useEffect, useState, useRef } from 'react'
import { createRoot } from 'react-dom/client'

import { Select } from 'antd';
import sanitizeHtml from 'sanitize-html';
import ContentEditable from 'react-contenteditable'

function unescapeHTML(string) {
  const el = document.createElement("span");
  el.innerHTML = string;
  return el.innerText;
}

const DeliveryFormPreview = ({ title, text, onChange }) => {

  const titleRef = useRef(title);
  const textRef = useRef(text);

  useEffect(() => {
    titleRef.current = title
  }, [ title ])

  useEffect(() => {
    textRef.current = text
  }, [ text ])

  return (
    <section className="homepage-delivery">
      <div className="homepage-delivery-text">
        <ContentEditable
          tagName="h2"
          html={titleRef.current}
          onChange={(e) => {
            titleRef.current = e.target.value
          }}
          onBlur={() => {
            titleRef.current = sanitizeHtml(unescapeHTML(titleRef.current), {
              // https://github.com/apostrophecms/sanitize-html?tab=readme-ov-file#what-if-i-dont-want-to-allow-any-tags
              allowedTags: [],
              allowedAttributes: {},
            });
            onChange({
              title: titleRef.current,
              text: textRef.current,
            })
          }} />
        <ContentEditable
          tagName="p"
          html={textRef.current}
          onChange={(e) => {
            textRef.current = e.target.value
          }}
          onBlur={() => {
            textRef.current = sanitizeHtml(unescapeHTML(textRef.current), {
              // https://github.com/apostrophecms/sanitize-html?tab=readme-ov-file#what-if-i-dont-want-to-allow-any-tags
              allowedTags: [],
              allowedAttributes: {},
            });
            onChange({
              title: titleRef.current,
              text: textRef.current,
            })
          }} />
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
    this.title = data.title || `Lorem ipsum dolor sit amet`
    this.text = data.text || `Lorem ipsum dolor sit amet, consectetur adipiscing elit, sed do eiusmod tempor incididunt ut labore et dolore magna aliqua.`
  }

  showPreview (wrapper) {
    const formPreviewEl = document.createElement('div')
    createRoot(formPreviewEl).render(<DeliveryFormPreview
      title={ this.title }
      text={ this.text }
      onChange={({ title, text }) => {
        this.title = title
        this.text = text
      }} />)
    wrapper.appendChild(formPreviewEl)
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
        this.showPreview(wrapper)
      }}
      options={this.config.forms.map((f, i) => ({ value: f['@id'], label: `Form #${i + 1}` }))}
    />);
    wrapper.appendChild(selectEl)

    if (this.form) {
      this.showPreview(wrapper)
    }

    return wrapper;
  }

  save(blockContent) {
    return {
      form: this.form,
      title: this.title,
      text: this.text,
    }
  }
}
