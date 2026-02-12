import React from 'react'
import { createRoot } from 'react-dom/client'
import ReactMarkdown from 'react-markdown'
import CodeMirror from 'codemirror/lib/codemirror'
import 'codemirror/mode/markdown/markdown'
import Dropzone from 'dropzone'
import EditorJS from '@editorjs/editorjs';
import ShopCollection from './editorjs/shop-collection'

import 'codemirror/lib/codemirror.css'
import 'codemirror/theme/monokai.css'

import './form.scss'

import 'swiper/css';
import 'swiper/css/navigation'
import 'swiper/css/free-mode'

import '../restaurant/list.scss'

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

  const root = createRoot(preview)

  cm.on('change', (editor) => {
    root.render(<ReactMarkdown>{ editor.getValue() }</ReactMarkdown>)
  })

  root.render(<ReactMarkdown>{ textarea.value }</ReactMarkdown>)

})

const dzEl = document.getElementById('banner-dropzone')

if (dzEl) {
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
}

// https://editorjs.io/configuration/#change-the-default-block

const editorEl = document.getElementById('editorjs');

if (editorEl) {
  const editor = new EditorJS({
    /**
     * Id of Element that should contain Editor instance
     */
    holder: 'editorjs',
    tools: {
      shop_collection: {
        class: ShopCollection,
        config: {
          cuisines: JSON.parse(editorEl.dataset.cuisines),
          shopTypes: JSON.parse(editorEl.dataset.shopTypes),
        }
      }
    },
    autofocus: true,
    /**
     * onChange callback
     */
    onChange: (api, event) => {
      editor.save()
        .then((savedData) => {
          // console.log('SAVED', savedData);
        })
        .catch((error) => {
          console.log('EditorJS save error', error)
        })
    }
  });
}
