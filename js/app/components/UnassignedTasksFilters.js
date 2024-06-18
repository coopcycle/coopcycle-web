import React from 'react'

import { setFilterValue } from '../dashboard/redux/actions'
import { useDispatch, useSelector } from 'react-redux'
import { selectAllTags, selectFiltersSetting, selectOrganizationsLoading } from '../dashboard/redux/selectors'
import IncludeExcludeMultiSelect from './IncludeExcludeMultiSelect'
import { findTagFromSlug } from '../dashboard/utils'
import { useTranslation } from 'react-i18next'
import { selectAllOrganizations } from '../../shared/src/logistics/redux/selectors'
import { Tooltip } from 'antd'


export default () => {

  const dispatch = useDispatch()
  const { t } = useTranslation()

  const allTags = useSelector(selectAllTags),
    allOrganizations = useSelector(selectAllOrganizations),
    organizationsLoading = useSelector(selectOrganizationsLoading),
    tagOptions = allTags.map((tag) => {return {...tag, isTag: true, label: tag.name, value: tag.slug}}),
    organizationOptions = allOrganizations.length > 0 ? allOrganizations.map(val => {return {...val, label: val.name, value: val.name}}) : [{value: '', label: `${t('ADMIN_DASHBOARD_LOADING_ORGS')}`, isDisabled: true}],
    options = Array.prototype.concat(organizationOptions, tagOptions)

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
    <>
      <Tooltip title={ t('ADMIN_DASHBOARD_FILTERS_TAGS_AND_ORGS_HELP_TEXT') }>
        <div> {/* Needed for the Tooltip to work */}
          <IncludeExcludeMultiSelect
            placeholder={ t('ADMIN_DASHBOARD_FILTERS_TAGS_AND_ORGS_PLACEHOLDER') }
            onChange={ onChange }
            selectOptions={ options }
            defaultValue={ defaultDisplayedValue }
            isLoading={ organizationsLoading }
          />
        </div>
      </Tooltip>
    </>
  )
}
