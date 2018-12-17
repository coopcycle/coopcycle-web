import React from 'react'
import { render } from 'react-dom'
import Select from 'react-select'
import _ from 'lodash'
import chroma from 'chroma-js'

const tagAsOption = tag => ({
  ...tag,
  value: tag.id,
  label: tag.name
})

const styles = {
  option: (styles, { data, isDisabled, isFocused, isSelected }) => {
    const color = chroma(data.color)

    return {
      ...styles,
      backgroundColor: isDisabled
        ? null
        : isSelected ? data.color : isFocused ? color.alpha(0.1).css() : null,
      color: isDisabled
        ? '#ccc'
        : isSelected
          ? chroma.contrast(color, 'white') > 2 ? 'white' : 'black'
          : data.color,
      cursor: isDisabled ? 'not-allowed' : 'default',
    }
  },
  multiValue: (styles, { data }) => ({
    ...styles,
    backgroundColor: data.color,
  }),
  multiValueLabel: (styles, { data }) => {
    const color = chroma(data.color)

    return {
      ...styles,
      color: chroma.contrast(color, 'white') > 2 ? 'white' : 'black'
    }
  },
  multiValueRemove: (styles, { data }) => {
    const color = chroma(data.color)

    return {
      ...styles,
      color: chroma.contrast(color, 'white') > 2 ? 'white' : 'black',
      ':hover': {
        backgroundColor: color.alpha(0.1).css(),
        color: chroma.contrast(color, 'white') > 2 ? 'white' : 'black',
      }
    }
  },
}

export default function(el, options) {

  const selectOptions = _.map(options.tags, tagAsOption)
  const selectDefaultValue = _.map(options.defaultValue, tagAsOption)

  render(
    <Select
      defaultValue={ selectDefaultValue }
      isMulti
      options={ selectOptions }
      onChange={ options.onChange }
      styles={ styles } />, el)

}
