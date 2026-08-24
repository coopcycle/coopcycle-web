import React from 'react';
import { Alert } from 'antd';
import dayjs, { Dayjs } from 'dayjs';
import { useTranslation } from 'react-i18next';
import { useGetShiftComplianceQuery } from '../../../api/slice';
import { ComplianceViolation } from '../../../api/types';

type Props = {
  weekStart: Dayjs;
};

const fmtDate = (date?: string) => (date ? dayjs(date).format('ddd D MMM') : '');

/**
 * Warns (never blocks) when the week's assignments violate the configured
 * legal constraints (e.g. CCN Transport: max hours, rest times, breaks).
 */
export default function ComplianceAlert({ weekStart }: Props) {
  const { t } = useTranslation();

  const { data } = useGetShiftComplianceQuery({
    week: weekStart.format('YYYY-MM-DD'),
  });

  if (!data || !data.template || data.violations.length === 0) {
    return null;
  }

  const messageFor = (v: ComplianceViolation): string => {
    const params = {
      username: v.username,
      actual: v.actual,
      limit: v.limit,
      date: fmtDate(v.date),
      from: fmtDate(v.from),
      to: fmtDate(v.to),
      weeks: v.weeks,
      workedHours: v.workedHours,
      thresholdHours: v.thresholdHours,
    };
    switch (v.rule) {
      case 'maxDailyHours':
        return t('SHIFT_COMPLIANCE_MAX_DAILY_HOURS', params);
      case 'maxWeeklyHours':
        return t('SHIFT_COMPLIANCE_MAX_WEEKLY_HOURS', params);
      case 'maxAvgWeeklyHours':
        return t('SHIFT_COMPLIANCE_MAX_AVG_WEEKLY_HOURS', params);
      case 'minDailyRestHours':
        return t('SHIFT_COMPLIANCE_MIN_DAILY_REST', params);
      case 'minWeeklyRestHours':
        return t('SHIFT_COMPLIANCE_MIN_WEEKLY_REST', params);
      case 'minBreakMinutes':
        return t('SHIFT_COMPLIANCE_MIN_BREAK', params);
      case 'maxConsecutiveDays':
        return t('SHIFT_COMPLIANCE_MAX_CONSECUTIVE_DAYS', params);
      default:
        return `${v.username}: ${v.rule}`;
    }
  };

  const templateName = t(`SHIFT_COMPLIANCE_TEMPLATE_${data.template}`, {
    defaultValue: data.template,
  });

  return (
    <Alert
      type="warning"
      showIcon
      className="shift-compliance-alert"
      message={t('SHIFT_COMPLIANCE_TITLE', {
        count: data.violations.length,
        template: templateName,
      })}
      description={
        <ul className="shift-compliance-alert__list">
          {data.violations.map((v, i) => (
            <li key={i}>{messageFor(v)}</li>
          ))}
        </ul>
      }
    />
  );
}
