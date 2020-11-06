import React from 'react'
import axios from 'axios'
import { render, unmountComponentAtNode } from 'react-dom'
import { Switch } from 'antd'

import SpreadsheetDropzone from '../components/SpreadsheetDropzone'

const baseURL = location.protocol + '//' + location.hostname

$('#products-import-modal').on('show.bs.modal', function () {

  const el = this.querySelector('[data-spreadsheet-dropzone]')

  const { url, ...props } = el.dataset

  const params = props.params ? JSON.parse(props.params) : {}

  render(
    <SpreadsheetDropzone
      url={ url }
      params={ params }
      onSuccess={ () => window.document.location.reload() } />, el)
})

$('#products-import-modal').on('hidden.bs.modal', function () {
  const el = this.querySelector('[data-spreadsheet-dropzone]')
  unmountComponentAtNode(el)
})

document.querySelectorAll('table[data-entity="product"] tbody tr').forEach(row => {

  const cell = row.querySelector('[data-cell="toggle"]')
  cell.innerHTML = ''

  const enabled = JSON.parse(row.dataset.enabled)
  const iri = row.dataset.iri

  const container = document.createElement('div')
  cell.appendChild(container)

  render(
    <Switch
      size="small"
      onChange={ checked => {
        $.getJSON(window.Routing.generate('profile_jwt')).then(result => {
          axios({
            method: 'put',
            url: baseURL + iri,
            data: {
              enabled: checked
            },
            headers: {
              'Accept': 'application/ld+json',
              'Content-Type': 'application/ld+json',
              Authorization: `Bearer ${result.jwt}`
            }
          })
        })
      }}
      defaultChecked={ enabled }
    />, container)
})
