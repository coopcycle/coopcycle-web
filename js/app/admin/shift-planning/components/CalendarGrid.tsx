import React, { useRef, useState } from 'react';
import { App, Tooltip } from 'antd';
import { StarFilled } from '@ant-design/icons';
import { Dayjs } from 'dayjs';
import dayjs from 'dayjs';
import { useTranslation } from 'react-i18next';
import { usePutShiftMutation } from '../../../api/slice';
import { Shift } from '../../../api/types';
import ShiftCard from './ShiftCard';
import { layoutOverlapping, minutesFromMidnight, shiftIsOnDay } from '../utils/date';

const HOUR_HEIGHT = 48;
const MIN_BLOCK_HEIGHT = 20;
const HOURS = [...Array(24)].map((_, h) => h);
// Must match the CSS width of .shift-planning-calendar__hours
const HOURS_GUTTER = 56;
const SNAP_MINUTES = 15;
const MIN_DURATION = 15;

type Props = {
  weekStart: Dayjs;
  shifts: Shift[];
  onCreate: (day: Dayjs, time: Dayjs) => void;
  onEdit: (shift: Shift) => void;
  typeColors?: Record<string, string>;
  bankHolidays?: Record<string, string>;
};

type DragMode = 'move' | 'resize-start' | 'resize-end';

type DragState = {
  mode: DragMode;
  shift: Shift;
  pointerId: number;
  origDayIndex: number;
  origStartMin: number;
  origEndMin: number;
  pointerStartY: number;
  // Live values, updated on pointermove
  dayIndex: number;
  startMin: number;
  endMin: number;
  moved: boolean;
};

const snap = (minutes: number) =>
  Math.round(minutes / SNAP_MINUTES) * SNAP_MINUTES;

const toTime = (minutes: number) =>
  `${String(Math.floor(minutes / 60)).padStart(2, '0')}:${String(
    minutes % 60,
  ).padStart(2, '0')}:00`;

/**
 * X = days, Y = hours of the day. Shifts are positioned/sized by their
 * actual start/end time; overlapping shifts on the same day share width
 * side by side (see utils/date.ts#layoutOverlapping).
 *
 * Blocks can be dragged (whole block = move, including to another day) and
 * resized (top/bottom edge = change start/end time), snapping to 15 min.
 * The change is saved on drop via PUT /api/shifts/{id}. Dragging relies on
 * pointer capture, so the source block stays mounted (hidden) while a ghost
 * follows the pointer.
 */
export default function CalendarGrid({
  weekStart,
  shifts,
  onCreate,
  onEdit,
  typeColors,
  bankHolidays,
}: Props) {
  const { t } = useTranslation();
  const { message } = App.useApp();

  const [putShift] = usePutShiftMutation();

  const days = [...Array(7)].map((_, i) => weekStart.add(i, 'day'));
  const today = dayjs().format('YYYY-MM-DD');

  const bodyRef = useRef<HTMLDivElement>(null);
  const [drag, setDrag] = useState<DragState | null>(null);
  // Suppresses the click that fires right after a drag ends, so dropping a
  // block doesn't also open the edit modal / create a new shift
  const didDragRef = useRef(false);

  const startDrag = (
    e: React.PointerEvent,
    mode: DragMode,
    shift: Shift,
    dayIndex: number,
  ) => {
    // Left button / primary touch only, one drag at a time
    if (e.button !== 0 || drag) {
      return;
    }
    e.preventDefault();
    e.stopPropagation();
    (e.currentTarget as HTMLElement).setPointerCapture(e.pointerId);

    const startMin = minutesFromMidnight(shift.startsAt);
    const endMin = minutesFromMidnight(shift.endsAt);

    setDrag({
      mode,
      shift,
      pointerId: e.pointerId,
      origDayIndex: dayIndex,
      origStartMin: startMin,
      origEndMin: endMin,
      pointerStartY: e.clientY,
      dayIndex,
      startMin,
      endMin,
      moved: false,
    });
  };

  const onDragMove = (e: React.PointerEvent) => {
    if (!drag || e.pointerId !== drag.pointerId) {
      return;
    }

    const deltaMin = snap(((e.clientY - drag.pointerStartY) / HOUR_HEIGHT) * 60);

    let { dayIndex, startMin, endMin } = drag;

    if (drag.mode === 'move') {
      const duration = drag.origEndMin - drag.origStartMin;
      startMin = Math.min(
        Math.max(0, drag.origStartMin + deltaMin),
        24 * 60 - duration,
      );
      endMin = startMin + duration;

      const rect = bodyRef.current?.getBoundingClientRect();
      if (rect) {
        const colWidth = (rect.width - HOURS_GUTTER) / 7;
        dayIndex = Math.min(
          6,
          Math.max(0, Math.floor((e.clientX - rect.left - HOURS_GUTTER) / colWidth)),
        );
      }
    } else if (drag.mode === 'resize-start') {
      startMin = Math.min(
        Math.max(0, drag.origStartMin + deltaMin),
        drag.origEndMin - MIN_DURATION,
      );
    } else {
      endMin = Math.max(
        Math.min(24 * 60, drag.origEndMin + deltaMin),
        drag.origStartMin + MIN_DURATION,
      );
    }

    const moved =
      drag.moved ||
      dayIndex !== drag.origDayIndex ||
      startMin !== drag.origStartMin ||
      endMin !== drag.origEndMin;

    setDrag({ ...drag, dayIndex, startMin, endMin, moved });
  };

  const saveDrag = async (current: DragState) => {
    const { shift } = current;
    const date = days[current.dayIndex].format('YYYY-MM-DD');

    try {
      await putShift({
        '@id': shift['@id'],
        type: shift.type,
        startsAt: `${date}T${toTime(current.startMin)}`,
        endsAt: `${date}T${toTime(current.endMin)}`,
        slots: shift.slots,
        breakMinutes: shift.breakMinutes,
        comment: shift.comment,
        users: shift.assignments.map(a => a.user['@id']),
      }).unwrap();
      message.success(t('SHIFT_PLANNING_SAVED'));
    } catch {
      message.error(t('SHIFT_PLANNING_ERROR'));
    }
  };

  const onDragEnd = (e: React.PointerEvent) => {
    if (!drag || e.pointerId !== drag.pointerId) {
      return;
    }
    setDrag(null);

    if (!drag.moved) {
      return;
    }

    didDragRef.current = true;
    setTimeout(() => {
      didDragRef.current = false;
    }, 0);

    void saveDrag(drag);
  };

  const onDragCancel = (e: React.PointerEvent) => {
    if (drag && e.pointerId === drag.pointerId) {
      setDrag(null);
    }
  };

  const onColumnClick = (
    day: Dayjs,
    e: React.MouseEvent<HTMLDivElement>,
  ) => {
    if (didDragRef.current) {
      return;
    }
    const rect = e.currentTarget.getBoundingClientRect();
    const offsetY = e.clientY - rect.top;
    const rawMinutes = (offsetY / HOUR_HEIGHT) * 60;
    const snapped = Math.min(
      23 * 60 + 30,
      Math.max(0, Math.round(rawMinutes / 30) * 30),
    );
    const time = day.hour(Math.floor(snapped / 60)).minute(snapped % 60);
    onCreate(day, time);
  };

  const renderBlock = (
    shift: Shift,
    dayIndex: number,
    col: number,
    cols: number,
    isGhost: boolean,
  ) => {
    const startMin = isGhost && drag ? drag.startMin : minutesFromMidnight(shift.startsAt);
    const endMin = isGhost && drag ? drag.endMin : minutesFromMidnight(shift.endsAt);

    const top = (startMin / 60) * HOUR_HEIGHT;
    const height = Math.max(
      MIN_BLOCK_HEIGHT,
      ((endMin - startMin) / 60) * HOUR_HEIGHT,
    );

    // The source block stays mounted (hidden) while dragging so pointer
    // capture isn't lost; the ghost shows the live times instead
    const isDragSource = !isGhost && drag?.shift['@id'] === shift['@id'];

    const displayShift: Shift =
      isGhost && drag
        ? {
            ...shift,
            startsAt: `${days[dayIndex].format('YYYY-MM-DD')}T${toTime(startMin)}`,
            endsAt: `${days[dayIndex].format('YYYY-MM-DD')}T${toTime(endMin)}`,
          }
        : shift;

    return (
      <div
        key={isGhost ? `${shift['@id']}--ghost` : shift['@id']}
        className={[
          'shift-planning-calendar__block',
          isGhost ? 'shift-planning-calendar__block--ghost' : '',
          isDragSource ? 'shift-planning-calendar__block--source' : '',
        ].join(' ')}
        style={{
          top,
          height,
          left: `${(col / cols) * 100}%`,
          width: `${(1 / cols) * 100}%`,
        }}
        onPointerDown={
          isGhost ? undefined : e => startDrag(e, 'move', shift, dayIndex)
        }
        onPointerMove={isGhost ? undefined : onDragMove}
        onPointerUp={isGhost ? undefined : onDragEnd}
        onPointerCancel={isGhost ? undefined : onDragCancel}
        onClickCapture={e => {
          if (didDragRef.current) {
            e.preventDefault();
            e.stopPropagation();
          }
        }}>
        <ShiftCard
          shift={displayShift}
          onClick={onEdit}
          typeColors={typeColors}
          showAssignees
        />
        {!isGhost && (
          <>
            <div
              className="shift-planning-calendar__resize-handle shift-planning-calendar__resize-handle--top"
              onPointerDown={e => startDrag(e, 'resize-start', shift, dayIndex)}
            />
            <div
              className="shift-planning-calendar__resize-handle shift-planning-calendar__resize-handle--bottom"
              onPointerDown={e => startDrag(e, 'resize-end', shift, dayIndex)}
            />
          </>
        )}
      </div>
    );
  };

  return (
    <div className="shift-planning-calendar__container">
      <div className="shift-planning-calendar">
        <div className="shift-planning-calendar__header">
          <div className="shift-planning-calendar__hours-header" />
          {days.map(day => {
            const dayKey = day.format('YYYY-MM-DD');
            const holidayName = bankHolidays?.[dayKey];

            return (
              <div
                key={dayKey}
                className={`shift-planning__header-cell ${
                  dayKey === today ? 'shift-planning__header-cell--today' : ''
                } ${holidayName ? 'shift-planning__header-cell--holiday' : ''}`}>
                {holidayName ? (
                  <Tooltip
                    title={t('SHIFT_PLANNING_BANK_HOLIDAY', {
                      name: holidayName,
                    })}>
                    <StarFilled className="shift-planning__holiday-icon" />
                    {day.format('ddd DD MMM')}
                  </Tooltip>
                ) : (
                  day.format('ddd DD MMM')
                )}
              </div>
            );
          })}
        </div>
        <div className="shift-planning-calendar__body" ref={bodyRef}>
          <div className="shift-planning-calendar__hours">
            {HOURS.map(h => (
              <div
                key={h}
                className="shift-planning-calendar__hour-label"
                style={{ height: HOUR_HEIGHT }}>
                {String(h).padStart(2, '0')}:00
              </div>
            ))}
          </div>
          {days.map((day, dayIndex) => {
            const dayShifts = shifts.filter(s => shiftIsOnDay(s, day));
            const laidOut = layoutOverlapping(dayShifts);

            return (
              <div
                key={day.format('YYYY-MM-DD')}
                className="shift-planning-calendar__day-column"
                style={{
                  height: HOUR_HEIGHT * 24,
                  backgroundSize: `100% ${HOUR_HEIGHT}px`,
                }}
                onClick={e => onColumnClick(day, e)}>
                {laidOut.map(({ shift, col, cols }) =>
                  renderBlock(shift, dayIndex, col, cols, false),
                )}
                {drag &&
                  drag.dayIndex === dayIndex &&
                  renderBlock(drag.shift, dayIndex, 0, 1, true)}
              </div>
            );
          })}
        </div>
      </div>
    </div>
  );
}
