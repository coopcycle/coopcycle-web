import React from 'react'
import { createRoot } from 'react-dom/client'
import { ColorPicker } from 'antd'

const PRESETS = [
  {
    colors: [
      '#D0021B',
      '#F5A623',
      '#F8E71C',
      '#8B572A',
      '#7ED321',
      '#417505',
      '#BD10E0',
      '#9013FE',
      '#4A90E2',
      '#50E3C2',
      '#B8E986',
    ],
  },
]

export default function(el) {
  const div = document.createElement('div')
  el.parentNode.insertBefore(div, el.nextSibling)
  el.type = 'hidden'
  const initialValue = el.value || '#ffffff'

  createRoot(div).render(
    <ColorPicker
      disabledAlpha
      presets={PRESETS}
      defaultValue={initialValue}
      onChangeComplete={color => {
        el.value = '#' + color.toHex()
      }}
    />,
  )
}
