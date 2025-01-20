import React, { useState } from 'react'
import { Select, Tag } from 'antd'

const options = [
  {
    value: '#2db7f5',
    label: 'urgent',
    backgroundColor: '#2db7f580',
  },
  {
    value: '#eb4034',
    label: 'important',
    backgroundColor: '#eb403480',
  },
  {
    value: '#87d068',
    label: 'rapide',
    backgroundColor: '#87d06880',
  },
  {
    value: '#108ee9',
    label: 'eco',
    backgroundColor: '#108ee980',
  },
]
const tagRender = props => {
  const { label, value, closable, onClose } = props
  const onPreventMouseDown = event => {
    event.preventDefault()
    event.stopPropagation()
  }
  return (
    <Tag
      color={value}
      onMouseDown={onPreventMouseDown}
      closable={closable}
      onClose={onClose}
      style={{
        marginRight: 3,
      }}>
      {label}
    </Tag>
  )
}

export default () => {
  const [selectedOptions, setSelectedOptions] = useState([])

  console.log(selectedOptions)
  const handleChange = values => {
    const selectedOptions = []

    for (const value of values) {
      const foundOptions = options.find(option => option.value === value)
      selectedOptions.push(foundOptions.label)
    }
    setSelectedOptions(selectedOptions)
  }

  return (
    <>
      <div className="tags__title block mb-2 font-weight-bold">Tags</div>
      <Select
        mode="multiple"
        showArrow
        tagRender={tagRender}
        style={{
          width: '100%',
        }}
        onChange={value => handleChange(value)}
        optionLabelProp="label">
        {options.map(option => (
          <Select.Option
            key={option.value}
            value={option.value}
            label={option.label}>
            <div className="option__label">
              <span
                style={{
                  backgroundColor: option.backgroundColor,
                  padding: '0.5em',
                }}>
                {option.label}
              </span>
            </div>
          </Select.Option>
        ))}
      </Select>
    </>
  )
}
