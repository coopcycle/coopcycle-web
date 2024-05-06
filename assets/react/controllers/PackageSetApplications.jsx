import React from 'react'
import Impl from '../../../js/app/components/Applications'

export default function PackageSetApplications(props) {
  const { packageSetId } = props,
    url = window.Routing.generate('api_package_sets_applications_item', {id: packageSetId})
  props = {...props, url}
  return (<Impl {...props} />)
}
