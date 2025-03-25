import React from 'react'
import { createRoot } from 'react-dom/client'
import { Switch } from 'antd'
import Popconfirm from 'antd/lib/popconfirm'

import SpreadsheetDropzone from '../components/SpreadsheetDropzone'
import createHttpClient from '../client'

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

const fetchToken = window.Routing.generate('profile_jwt')

$.getJSON(fetchToken)
  .then(result => {

    const httpClient = createHttpClient(
      result.jwt,
      () => new Promise((resolve) => {
        // TODO Check response is OK, reject promise
        $.getJSON(fetchToken).then(result => resolve(result.jwt))
      })
    )

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

      const deleteCell = row.querySelector('[data-cell="delete"]')

      createRoot(deleteCell).render(
        <Popconfirm
          placement="left"
          title="Are you sure？"
          okText="Yes"
          cancelText="No"
          onConfirm={ () => {
            httpClient.delete(iri).then(res => {
              if (res.status === 204) {
                window.document.location.reload()
              }
            })
          }}>
          <a href="#">
            <span className="glyphicon glyphicon-trash"></span>
          </a>
        </Popconfirm>
      )

    })
  })
