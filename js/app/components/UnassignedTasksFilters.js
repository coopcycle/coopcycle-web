import React, { useEffect, useState } from 'react'

import { setFilterValue } from '../dashboard/redux/actions'
import { useDispatch, useSelector } from 'react-redux'
import { selectAllTags, selectFiltersSetting } from '../dashboard/redux/selectors'
import { createClient } from '../dashboard/utils/client'
import IncludeExcludeMultiSelect from './IncludeExcludeMultiSelect'
import { findTagFromSlug } from '../dashboard/utils'
import { useTranslation } from 'react-i18next'
import { selectAllOrganizations } from '../../shared/src/logistics/redux/selectors'


export default () => {

  const dispatch = useDispatch()
  const { t } = useTranslation()

  const allTags = useSelector(selectAllTags),
    allOrganizations = useSelector(selectAllOrganizations),
    organizationOptions = allOrganizations.map(val => {return {...val, label: val.name, value: val.name}}),
    tagOptions = allTags.map((tag) => {return {...tag, isTag: true, label: tag.name, value: tag.slug}}),
    options = Array.prototype.concat(tagOptions, organizationOptions)

  const onChange = (selected) => {
    // dispatch action
    dispatch(setFilterValue('unassignedTasksFilters', {
      excludedTags: selected.filter(opt => opt.isTag && opt.isExclusion).map(opt => opt.value),
      includedTags: selected.filter(opt => opt.isTag && !opt.isExclusion).map(opt => opt.value),
      excludedOrgs: selected.filter(opt => !opt.isTag && opt.isExclusion).map(opt => opt.value),
      includedOrgs: selected.filter(opt => !opt.isTag && !opt.isExclusion).map(opt => opt.value)
    }))
  }

  const { unassignedTasksFilters } = useSelector(selectFiltersSetting)
  const defaultDisplayedValue = Array.prototype.concat(
    unassignedTasksFilters.excludedTags.map((slug) => {
      const tag = findTagFromSlug(slug, allTags)
      return {...tag, label: '-'+tag.name, value: slug, isExclusion: true}
    }),
    unassignedTasksFilters.includedTags.map((slug) => {
      const tag = findTagFromSlug(slug, allTags)
      return {...tag, label: tag.name, value: slug}
    }),
    unassignedTasksFilters.excludedOrgs.map((val) => {return {label: '-'+ val,value:val, isExclusion: true}}),
    unassignedTasksFilters.includedOrgs.map((val) => {return {label: val, value: val}})
  )

  return (
    <IncludeExcludeMultiSelect
      placeholder={ t('TAGS_SELECT_PLACEHOLDER') }
      onChange={ onChange }
      selectOptions={ options }
      defaultValue={ defaultDisplayedValue }
    />
  )
}
