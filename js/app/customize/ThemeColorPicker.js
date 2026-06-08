import React, { useState } from 'react'
import { Flex } from 'antd'
import ColorPicker from './color-picker'

// https://daisyui.com/docs/colors/
const DEFAULTS = {
  'primary':           '#422ad5',
  'primary-content':   '#e0e7ff',
  'secondary':         '#f43098',
  'secondary-content': '#f9e4f0',
}

const GROUPS = [
  {
    groupKey:    'primaryGroup',
    helpKey:     'helpPrimaryGroup',
    fields: [
      { key: 'primary',         inputId: 'customize_theme_primary',          labelKey: 'primary' },
      { key: 'primary-content', inputId: 'customize_theme_primary-content',  labelKey: 'primaryContent' },
    ],
  },
  {
    groupKey:    'secondaryGroup',
    helpKey:     'helpSecondaryGroup',
    fields: [
      { key: 'secondary',         inputId: 'customize_theme_secondary',         labelKey: 'secondary' },
      { key: 'secondary-content', inputId: 'customize_theme_secondary-content', labelKey: 'secondaryContent' },
    ],
  },
]

export default function ThemeColorPicker({ initialValues, labels = {} }) {
  const [values, setValues] = useState(
    Object.fromEntries(
      Object.entries(DEFAULTS).map(([k, def]) => [k, initialValues[k] || def])
    )
  )

  const handleChange = (key, inputId, color) => {
    const hex = color.toHexString()
    setValues(prev => ({ ...prev, [key]: hex }))
    const input = document.getElementById(inputId)
    if (input) input.value = hex
  }

  return (
    <Flex vertical gap="large">
      {GROUPS.map(({ groupKey, helpKey, fields }) => (
        <div key={groupKey}>
          <div style={{ fontWeight: 600, marginBottom: 4 }}>
            {labels[groupKey] || groupKey}
          </div>
          {labels[helpKey] && (
            <div style={{ fontSize: 12, color: '#888', marginBottom: 8 }}>
              {labels[helpKey]}
            </div>
          )}
          <Flex gap="large" wrap="wrap">
            {fields.map(({ key, inputId, labelKey }) => (
              <div key={key}>
                <div style={{ marginBottom: 4, fontSize: 12, color: '#666' }}>
                  {labels[labelKey] || labelKey}
                </div>
                <ColorPicker
                  value={values[key]}
                  showText
                  onChange={(color) => handleChange(key, inputId, color)}
                />
              </div>
            ))}
          </Flex>
        </div>
      ))}
    </Flex>
  )
}
