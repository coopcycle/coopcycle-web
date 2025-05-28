import React from 'react'
import Impl from '../../../js/app/components/Applications'

export default function ShowApplications(props) {
  const { pricingRuleSetId } = props,
    url = window.Routing.generate('_api_/pricing_rule_sets/{id}/applications_get', {id: pricingRuleSetId})
  props = {...props, url}
  return (<Impl {...props} />)
}
