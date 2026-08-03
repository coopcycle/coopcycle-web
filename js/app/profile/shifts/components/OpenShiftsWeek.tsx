import React from 'react';
import { App, Button, Empty, Popconfirm, Spin, Tag, Tooltip } from 'antd';
import { Dayjs } from 'dayjs';
import { useTranslation } from 'react-i18next';
import {
  useApplyToShiftMutation,
  useGetMeQuery,
  useGetOpenShiftsQuery,
  useGetShiftSettingsQuery,
  useUnapplyFromShiftMutation,
} from '../../../api/slice';
import { Shift } from '../../../api/types';
import ShiftCard from '../../../admin/shift-planning/components/ShiftCard';
import {
  shiftIsOnDay,
  sortByStart,
} from '../../../admin/shift-planning/utils/date';

type Props = {
  weekStart: Dayjs;
};

/**
 * The shifts of the selected week couriers can apply to — only weeks whose
 * schedule has been published appear here. A free slot means applying
 * assigns you right away (first come first served); a full shift means
 * joining the waitlist, promoted automatically when someone unapplies.
 * Shifts requiring skills you don't have can't be applied to.
 */
export default function OpenShiftsWeek({ weekStart }: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const after = weekStart.format('YYYY-MM-DD');
  const before = weekStart.add(6, 'day').format('YYYY-MM-DD');

  const { data, isFetching } = useGetOpenShiftsQuery({ after, before });
  const { data: me } = useGetMeQuery();
  const { data: shiftSettings } = useGetShiftSettingsQuery();

  const [applyToShift, { isLoading: isApplying }] = useApplyToShiftMutation();
  const [unapplyFromShift, { isLoading: isUnapplying }] =
    useUnapplyFromShiftMutation();

  const shifts = sortByStart(data?.['hydra:member'] ?? []);
  const days = [...Array(7)].map((_, i) => weekStart.add(i, 'day'));

  const mySkillIds = new Set((me?.skills ?? []).map(s => s['@id']));

  const onApply = async (shift: Shift) => {
    try {
      await applyToShift(shift['@id']).unwrap();
      message.success(t('SHIFT_PLANNING_APPLY_SUCCESS'));
    } catch {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  const onUnapply = async (shift: Shift) => {
    try {
      await unapplyFromShift(shift['@id']).unwrap();
      message.success(t('SHIFT_PLANNING_UNAPPLY_SUCCESS'));
    } catch {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  const renderAction = (shift: Shift) => {
    if (!me) {
      return null;
    }

    const isMine = shift.assignments.some(
      a => a.user.username === me.username,
    );
    if (isMine) {
      return (
        <Popconfirm
          title={t('SHIFT_PLANNING_UNAPPLY_CONFIRM')}
          onConfirm={() => onUnapply(shift)}>
          <Button size="small" danger loading={isUnapplying}>
            {t('SHIFT_PLANNING_UNAPPLY')}
          </Button>
        </Popconfirm>
      );
    }

    const waitlistIndex = shift.waitlist.findIndex(
      w => w.user.username === me.username,
    );
    if (waitlistIndex >= 0) {
      return (
        <>
          <Tag color="processing">
            {t('SHIFT_PLANNING_WAITLIST_POSITION', {
              position: waitlistIndex + 1,
            })}
          </Tag>
          <Button
            size="small"
            loading={isUnapplying}
            onClick={() => onUnapply(shift)}>
            {t('SHIFT_PLANNING_LEAVE_WAITLIST')}
          </Button>
        </>
      );
    }

    const missingSkills = shift.requiredSkills.filter(
      s => !mySkillIds.has(s['@id']),
    );
    if (missingSkills.length > 0) {
      return (
        <Tooltip
          title={t('SHIFT_PLANNING_APPLY_MISSING_SKILLS', {
            skills: missingSkills.map(s => s.name).join(', '),
          })}>
          <Button size="small" disabled>
            {t('SHIFT_PLANNING_APPLY')}
          </Button>
        </Tooltip>
      );
    }

    const isFull = shift.assignments.length >= shift.slots;
    if (isFull) {
      return (
        <Button
          size="small"
          loading={isApplying}
          onClick={() => onApply(shift)}>
          {t('SHIFT_PLANNING_JOIN_WAITLIST')}
        </Button>
      );
    }

    return (
      <Button
        size="small"
        type="primary"
        loading={isApplying}
        onClick={() => onApply(shift)}>
        {t('SHIFT_PLANNING_APPLY')}
      </Button>
    );
  };

  return (
    <Spin spinning={isFetching}>
      {shifts.length === 0 ? (
        <Empty description={t('SHIFT_PLANNING_NO_OPEN_SHIFTS')} />
      ) : (
        days.map(day => {
          const dayShifts = shifts.filter(s => shiftIsOnDay(s, day));
          if (dayShifts.length === 0) {
            return null;
          }
          return (
            <div key={day.format('YYYY-MM-DD')} className="mb-3">
              <strong>{day.format('dddd DD MMM')}</strong>
              {dayShifts.map(shift => (
                <div key={shift['@id']} className="open-shift-row">
                  <div className="open-shift-row__card">
                    <ShiftCard
                      shift={shift}
                      typeColors={shiftSettings?.typeColors}
                    />
                  </div>
                  <div className="open-shift-row__actions">
                    {renderAction(shift)}
                  </div>
                </div>
              ))}
            </div>
          );
        })
      )}
    </Spin>
  );
}
