import React from 'react'
import Impl from '../../../js/app/components/Applications'

export default function ShowApplications(props) {
  const { pricingRuleSetId } = props,
    url = window.Routing.generate('api_pricing_rule_sets_applications_item', {id: pricingRuleSetId})
  props = {...props, url}
  return (<Impl {...props} />)
}
