import React from 'react'
import { Select, Tag } from 'antd'

const options = [
  {
    value: '#2db7f5',
    label: 'urgent',
  },
  {
    value: '#f50',
    label: 'important',
  },
  {
    value: '#87d068',
    label: 'rapide',
  },
  {
    value: '#108ee9',
    label: 'eco',
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

export default () => (
  <>
    <div className="tags__title block mb-2 font-weight-bold">Tags</div>
    <Select
      mode="multiple"
      showArrow
      tagRender={tagRender}
      // defaultValue={}
      style={{
        width: '100%',
      }}
      optionLabelProp="label">
      {options.map(option => (
        <Select.Option
          key={option.value}
          value={option.value}
          label={option.label}
          style={{ backgroundColor: option.value }}>
          <div className={option.className}>{option.label}</div>
        </Select.Option>
      ))}
    </Select>
  </>
)
