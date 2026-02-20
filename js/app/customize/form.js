import React from 'react'
import { createRoot } from 'react-dom/client'
import ReactMarkdown from 'react-markdown'
import CodeMirror from 'codemirror/lib/codemirror'
import 'codemirror/mode/markdown/markdown'
import Dropzone from 'dropzone'
import EditorJS from '@editorjs/editorjs';
import ShopCollection from './editorjs/shop-collection'

import HomepageEditor from './HomepageEditor'

import 'codemirror/lib/codemirror.css'
import 'codemirror/theme/monokai.css'

import './form.scss'

import 'swiper/css';
import 'swiper/css/navigation'
import 'swiper/css/free-mode'

import '../restaurant/list.scss'

import "dropzone/dist/dropzone.css"

import '@mdxeditor/editor/style.css'

import '@uppy/core/css/style.min.css';
import '@uppy/dashboard/css/style.min.css';

import 'skeleton-screen-css/dist/index.scss'

import '../delivery/homepage.scss'

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

const homepageEditorEl = document.getElementById('homepage-editor')

if (homepageEditorEl) {
  // console.log(homepageEditorEl.dataset.ctaIcon)
  createRoot(homepageEditorEl).render(
    <HomepageEditor
      blocks={JSON.parse(homepageEditorEl.dataset.blocks)}
      cuisines={JSON.parse(homepageEditorEl.dataset.cuisines)}
      shopTypes={JSON.parse(homepageEditorEl.dataset.shopTypes)}
      uploadEndpoint={homepageEditorEl.dataset.uploadEndpoint}
      deliveryForms={JSON.parse(homepageEditorEl.dataset.deliveryForms)}
      shopCollections={JSON.parse(homepageEditorEl.dataset.shopCollections)}
    />
  )
}
