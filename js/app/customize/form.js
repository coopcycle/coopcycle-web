import React from 'react'
import { render } from 'react-dom'
import ReactMarkdown from 'react-markdown'
import CodeMirror from 'codemirror/lib/codemirror'
import 'codemirror/mode/markdown/markdown'

import 'codemirror/lib/codemirror.css'
import 'codemirror/theme/monokai.css'

import './form.scss'

const textarea = document.getElementById('customize_aboutUs')
const preview = document.getElementById('preview')

const cm = CodeMirror.fromTextArea(textarea, {
  mode: "markdown",
  theme: "monokai"
})

cm.on('change', (editor) => {
  render(<ReactMarkdown source={ editor.getValue() } />, preview)
})

render(<ReactMarkdown source={ textarea.value } />, preview)

const customTermsTextarea = document.getElementById('customize_customTerms')
const customTermsPreview = document.getElementById('custom-terms-preview')

const cm2 = CodeMirror.fromTextArea(customTermsTextarea, {
  mode: "markdown",
  theme: "monokai"
})

cm2.on('change', (editor) => {
  render(<ReactMarkdown source={ editor.getValue() } />, customTermsPreview)
})

render(<ReactMarkdown source={ customTermsTextarea.value } />, customTermsPreview)
