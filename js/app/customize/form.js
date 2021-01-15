import React from 'react'
import { render } from 'react-dom'
import ReactMarkdown from 'react-markdown'
import CodeMirror from 'codemirror/lib/codemirror'
import 'codemirror/mode/markdown/markdown'
import Dropzone from 'dropzone'

import 'codemirror/lib/codemirror.css'
import 'codemirror/theme/monokai.css'

import './form.scss'

import "dropzone/dist/dropzone.css"

Dropzone.autoDiscover = false

document.querySelectorAll('textarea[data-preview]').forEach((textarea) => {

  const preview = document.querySelector(
    textarea.getAttribute('data-preview')
  )

  const cm = CodeMirror.fromTextArea(textarea, {
    mode: "markdown",
    theme: "monokai"
  })

  cm.on('change', (editor) => {
    render(<ReactMarkdown source={ editor.getValue() } />, preview)
  })

  render(<ReactMarkdown source={ textarea.value } />, preview)

})

const dzEl = document.getElementById('banner-dropzone')

new Dropzone(dzEl, {
  url: dzEl.dataset.dropzoneUrl,
  acceptedFiles: 'image/svg,image/svg+xml',
  // dictDefaultMessage: t('DROPZONE_DEFAULT_MESSAGE'),
  maxFiles: 1,
  // params,
  // Set clickable = false, to avoid limiting file explorers
  // behaving differently on different operating systems
  clickable: false,
  init: function() {
    const dz = this
    // @see https://github.com/enyo/dropzone/wiki/FAQ#how-to-show-files-already-stored-on-server
    if (dzEl.dataset.dropzoneImage) {
      $.ajax({
        type: 'HEAD',
        async: true,
        url: dzEl.dataset.dropzoneImage,
        success: function(message, text, jqXHR) {
          const filesize = jqXHR.getResponseHeader('Content-Length')
          const filename = dzEl.dataset.dropzoneImage.substr(dzEl.dataset.dropzoneImage.lastIndexOf('/') + 1)
          dz.displayExistingFile({ name: filename, size: filesize }, dzEl.dataset.dropzoneImage)
        }
      })
    }
  }
})
