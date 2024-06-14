import React from 'react'
import { useSelector } from 'react-redux'
import chroma from 'chroma-js'

import IncludeExcludeMultiSelect from './IncludeExcludeMultiSelect'

import { selectAllTags, selectFiltersSetting } from '../dashboard/redux/selectors'
import { findTagFromSlug } from '../dashboard/utils'
import { useTranslation } from 'react-i18next'

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
  menuPortal: base => ({ ...base, zIndex: 9 })
}

export default ({setFieldValue}) => {

  const allTags = useSelector(selectAllTags)
  const { t } = useTranslation()
  const { tags, excludedTags } = useSelector(selectFiltersSetting)

  const tagOptions = allTags.map((tag) => {return {...tag, isTag: true, label: tag.name, value: tag.slug}})
  const organizationOptions = []
  const initOptions = Array.prototype.concat(tagOptions, organizationOptions)

  const onChange = (selected) => {
    // set field values in FilterModalForm
    setFieldValue('tags', selected.filter(opt => opt.isTag && !opt.isExclusion).map(opt => opt.value))
    setFieldValue('excludedTags', selected.filter(opt => opt.isTag && opt.isExclusion).map(opt => opt.value))

    // do the same for orgs
  }

  const defaultDisplayedValue = Array.prototype.concat(
    excludedTags.map((slug) => {
      const tag = findTagFromSlug(slug, allTags)
      return {...tag, label: '-'+tag.name, value: slug, isExclusion: true}
    }),
    tags.map((slug) => {
      const tag = findTagFromSlug(slug, allTags)
      return {...tag, label: tag.name, value: slug}
    }),
    // unassignedTasksFilters.excludedOrgs.map((val) => {return {label: '-'+ val,value:val, isExclusion: true}}),
    // unassignedTasksFilters.includedOrgs.map((val) => {return {label: val, value: val}})
  )


  return (
    <IncludeExcludeMultiSelect
      placeholder={ t('TAGS_SELECT_PLACEHOLDER') }
      onChange={ onChange }
      selectOptions={ initOptions }
      defaultValue={ defaultDisplayedValue }
      selectProps={styles}
    />)
}
