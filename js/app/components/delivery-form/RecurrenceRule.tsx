import React from 'react'
import { rrulestr, RRule } from 'rrule'
import moment from 'moment'

import RecurrenceRuleAsText from './RecurrenceRuleAsText'

type Props = {
  rrule: string
  onClick: () => void
}

const RecurrenceRule = ({ rrule, onClick }: Props) => {
  const ruleObj: RRule = rrulestr(rrule, {
    dtstart: moment.utc().toDate(),
  })

  return (
    <span
      data-testid="recurrence-rule"
      className="list-group-item text-info cursor-pointer"
      onClick={onClick}>
      <i className="fa fa-clock-o mr-2"></i>
      <span>
        <span className="mr-1">
          <RecurrenceRuleAsText rrule={ruleObj} />
        </span>
      </span>
    </span>
  )
}

export default RecurrenceRule
