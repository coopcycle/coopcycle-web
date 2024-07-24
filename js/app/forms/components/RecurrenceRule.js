import React from 'react'
import { rrulestr } from 'rrule'
import moment from 'moment'

import RecurrenceRuleAsText from './RecurrenceRuleAsText'

export default ({ rrule, onClick }) => {
  const ruleObj = rrulestr(rrule, {
    dtstart: moment.utc().toDate(),
  })

  return (
    <span className="list-group-item text-info" onClick={onClick}>
      <i className="fa fa-clock-o mr-2"></i>
      <span>
        <span className="mr-1">
          <RecurrenceRuleAsText rrule={ruleObj} />
        </span>
      </span>
    </span>
  )
}
