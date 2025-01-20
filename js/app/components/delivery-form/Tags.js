import React, { useState } from 'react'
import { Select, Tag } from 'antd'
import { useFormikContext } from 'formik'

export default ({ tags, index }) => {
  const { setFieldValue } = useFormikContext()

  const tagRender = props => {
    const { label, color, closable, onClose } = props
    const onPreventMouseDown = event => {
      event.preventDefault()
      event.stopPropagation()
    }
    return (
      <Tag
        color={color}
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

  const handleChange = values => {
    setFieldValue(`tasks[${index}].tags`, values)
  }

  return (
    <>
      <div className="tags__title block mb-2 font-weight-bold">Tags</div>
      <Select
        mode="multiple"
        showArrow
        tagRender={props => {
          const tag = tags.find(tag => tag.name === props.value)
          return tagRender({ ...props, color: tag.color, label: tag.name })
        }}
        style={{
          width: '100%',
        }}
        onChange={value => handleChange(value)}
        optionLabelProp="label">
        {tags.map(tag => (
          <Select.Option key={tag.name} value={tag.name} label={tag.name}>
            <div className="option__label">
              <span
                style={{
                  backgroundColor: tag.color,
                  padding: '0.5em',
                }}>
                {tag.name}
              </span>
            </div>
          </Select.Option>
        ))}
      </Select>
    </>
  )
}
