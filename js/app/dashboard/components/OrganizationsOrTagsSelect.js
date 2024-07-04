import React from 'react'
import { useSelector } from 'react-redux'
import chroma from 'chroma-js'

import IncludeExcludeMultiSelect from '../../components/IncludeExcludeMultiSelect'

import { selectAllTags, selectFiltersSetting, selectTagsSelectOptions } from '../redux/selectors'
import { findTagFromSlug } from '../utils'
import { useTranslation } from 'react-i18next'
import { selectOrganizationsLoading, selectOrganizationsSelectOptions } from '../../../shared/src/logistics/redux/selectors'


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

  const { t } = useTranslation()
  const { tags, excludedTags, includedOrgs, excludedOrgs } = useSelector(selectFiltersSetting)

  const allTags = useSelector(selectAllTags)
  const tagOptions = useSelector(selectTagsSelectOptions)
  const organizationsLoading = useSelector(selectOrganizationsLoading)
  const organizationOptions = useSelector(selectOrganizationsSelectOptions)

  const initOptions = Array.prototype.concat(tagOptions, organizationOptions)

  const onChange = (selected) => {
    // set field values in FilterModalForm
    setFieldValue('tags', selected.filter(opt => opt.isTag && !opt.isExclusion).map(opt => opt.value))
    setFieldValue('excludedTags', selected.filter(opt => opt.isTag && opt.isExclusion).map(opt => opt.value))

    setFieldValue('includedOrgs', selected.filter(opt => !opt.isTag && !opt.isExclusion).map(opt => opt.value))
    setFieldValue('excludedOrgs', selected.filter(opt => !opt.isTag && opt.isExclusion).map(opt => opt.value))
  }

  const defaultDisplayedValue = Array.prototype.concat(
    excludedTags.map((slug) => {
      const tag = findTagFromSlug(slug, allTags)
      return {...tag, label: '-'+tag.name, value: slug, isExclusion: true, isTag:true}
    }),
    tags.map((slug) => {
      const tag = findTagFromSlug(slug, allTags)
      return {...tag, label: tag.name, value: slug, isTag:true}
    }),
    excludedOrgs.map((val) => {return {label: '-'+ val,value:val, isExclusion: true}}),
    includedOrgs.map((val) => {return {label: val, value: val}})
  )


  return (
    <>
      <IncludeExcludeMultiSelect
        placeholder={t('ADMIN_DASHBOARD_FILTERS_TAGS_AND_ORGS_PLACEHOLDER')}
        onChange={onChange}
        selectOptions={initOptions}
        defaultValue={defaultDisplayedValue}
        selectProps={styles}
        isLoading={organizationsLoading}
      />
      <p className='text-muted'>{ t('ADMIN_DASHBOARD_FILTERS_TAGS_AND_ORGS_HELP_TEXT') }</p>
    </>)
}
