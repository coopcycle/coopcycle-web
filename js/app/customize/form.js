import React from 'react'
import { render } from 'react-dom'
import ReactMarkdown from 'react-markdown'
import CodeMirror from 'codemirror/lib/codemirror'
import 'codemirror/mode/markdown/markdown'

import 'codemirror/lib/codemirror.css'
import 'codemirror/theme/monokai.css'

import './form.scss'

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
