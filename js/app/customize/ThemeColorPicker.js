import React, { useState } from 'react'
import { Flex } from 'antd'
import ColorPicker from './color-picker'

const COLORS = [
  { key: 'primary',           inputId: 'customize_theme_primary',          label: 'Primary' },
  { key: 'primary-content',   inputId: 'customize_theme_primary-content',  label: 'Primary content' },
  { key: 'secondary',         inputId: 'customize_theme_secondary',         label: 'Secondary' },
  { key: 'secondary-content', inputId: 'customize_theme_secondary-content', label: 'Secondary content' },
]

export default function ThemeColorPicker({ initialValues }) {
  const [values, setValues] = useState(initialValues)

  const handleChange = (key, inputId, color) => {
    const hex = color.toHexString()
    setValues(prev => ({ ...prev, [key]: hex }))
    const input = document.getElementById(inputId)
    if (input) input.value = hex
  }

  return (
    <Flex gap="large" wrap="wrap">
      {COLORS.map(({ key, inputId, label }) => (
        <div key={key}>
          <div style={{ marginBottom: 4, fontSize: 12, color: '#666' }}>{label}</div>
          <ColorPicker
            value={values[key] || undefined}
            showText
            onChange={(color) => handleChange(key, inputId, color)}
          />
        </div>
      ))}
    </Flex>
  )
}
