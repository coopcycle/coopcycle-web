import React from 'react'
import { render, unmountComponentAtNode } from 'react-dom'

import SpreadsheetDropzone from '../components/SpreadsheetDropzone'

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
