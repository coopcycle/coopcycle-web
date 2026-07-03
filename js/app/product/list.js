import React from 'react'
import { createRoot } from 'react-dom/client'
import { Switch } from 'antd'

import SpreadsheetDropzone from '../components/SpreadsheetDropzone'

const dropzoneEl = document.getElementById('products-import-modal').querySelector('[data-spreadsheet-dropzone]')
let dropzoneRoot

$('#products-import-modal').on('show.bs.modal', function () {

  dropzoneRoot = createRoot(dropzoneEl)

  const { url, ...props } = dropzoneEl.dataset

  const params = props.params ? JSON.parse(props.params) : {}

  dropzoneRoot.render(
    <SpreadsheetDropzone
      url={ url }
      params={ params }
      onSuccess={ () => window.document.location.reload() } />)
})

$('#products-import-modal').on('hidden.bs.modal', function () {
  dropzoneRoot.unmount();
})

const httpClient = new window._auth.httpClient();

document.querySelectorAll('table[data-entity="product"] tbody tr').forEach(row => {

  const cell = row.querySelector('[data-cell="toggle"]')
  cell.innerHTML = ''

  const enabled = JSON.parse(row.dataset.enabled)
  const iri = row.dataset.iri

  const container = document.createElement('div')
  cell.appendChild(container)

  createRoot(container).render(
    <Switch
      size="small"
      onChange={ checked => {
        httpClient.put(iri, { enabled: checked })
      }}
      defaultChecked={ enabled }
    />)

})
