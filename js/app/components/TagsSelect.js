import React from 'react'
import Select from 'react-select'
import _ from 'lodash'
import chroma from 'chroma-js'

import i18n from '../i18n'

const tagAsOption = tag => ({
  ...tag,
  value: tag.slug,
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

export default (props) => {

  const { tags, defaultValue, ...rest } = props

  let defaultValueAsTags = []
  if (defaultValue && Array.isArray(defaultValue)) {
    defaultValueAsTags = _.map(defaultValue, tag => {
      if (_.isString(tag)) {
        return _.find(tags, t => t.slug === tag)
      }

      return _.find(tags, t => t.slug === tag.slug)
    })
  }

  if (defaultValue && typeof defaultValue === 'string') {
    const slugs = defaultValue.split(/[ ]+/)
    defaultValueAsTags = slugs.map(slug => _.find(tags, t => t.slug === slug))
  }

  return (
    <Select
      defaultValue={ _.map(defaultValueAsTags, tagAsOption) }
      isMulti
      options={ _.map(tags, tagAsOption) }
      // https://github.com/coopcycle/coopcycle-web/issues/774
      // https://github.com/JedWatson/react-select/issues/3030
      menuPortalTarget={ document.body }
      styles={{
        ...styles,
        menuPortal: base => ({ ...base, zIndex: 9 })
      }}
      placeholder={ i18n.t('TAGS_SELECT_PLACEHOLDER') }
      { ...rest } />
  )
}
