import React from 'react'
import { rrulestr } from 'rrule'
import moment from 'moment'
import { toTextArgs } from '../utils/rrule'

export default ({ rrule, onClick }) => {
  const ruleObj = rrulestr(rrule.rule, {
    dtstart: moment.utc().toDate(),
  })

  const length = rrule.template['@type'] === 'hydra:Collection' ? rrule.template['hydra:member'].length : 1

  return (
    <span className="list-group-item text-info" onClick={ onClick }>
      <i className="fa fa-clock-o mr-2"></i>
      <span>
        <span className="font-weight-bold">{ rrule.orgName }</span>
        <span className="mx-1">›</span>
      </span>
      <span>{ `${ruleObj.toText(...toTextArgs())} (${length})` }</span>
    </span>
  )
}
