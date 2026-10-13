import React from 'react';
import { rrulestr, RRule } from 'rrule';
import moment from 'moment';
import { useTranslation } from 'react-i18next';

import RecurrenceRuleAsText from './RecurrenceRuleAsText';

type Props = {
  rrule: string;
  startDate?: string | null;
  endDate?: string | null;
  onClick: () => void;
};

const RecurrenceRule = ({ rrule, startDate, endDate, onClick }: Props) => {
  const { t } = useTranslation();

  const ruleObj: RRule = rrulestr(rrule, {
    dtstart: moment.utc().toDate(),
  });

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
        {(startDate || endDate) && (
          <small className="text-muted">
            {startDate
              ? `${t('RECURRENCE_START_DATE')}: ${moment(startDate).format('L')}`
              : null}
            {startDate && endDate ? ' — ' : null}
            {endDate
              ? `${t('RECURRENCE_END_DATE')}: ${moment(endDate).format('L')}`
              : null}
          </small>
        )}
      </span>
    </span>
  );
};

export default RecurrenceRule;
