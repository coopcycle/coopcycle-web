import React, { useEffect, useMemo, useRef, useState } from 'react';
import { Provider } from 'react-redux';
import { Badge, Button, Segmented, Space, Spin } from 'antd';
import { CarryOutOutlined } from '@ant-design/icons';
import { useHotkey } from '@tanstack/react-hotkeys';
import dayjs, { Dayjs } from 'dayjs';
import isoWeek from 'dayjs/plugin/isoWeek';
import { useTranslation } from 'react-i18next';
import { store } from './redux/store';
import {
  useGetBankHolidaysQuery,
  useGetHolidayRequestsQuery,
  useGetPlanningUsersQuery,
  useGetShiftSettingsQuery,
  useGetShiftsQuery,
} from '../../api/slice';
import { Shift, Uri } from '../../api/types';
import WeekNavigator from './components/WeekNavigator';
import PlanningGrid from './components/PlanningGrid';
import ShiftModal, { ShiftModalState } from './components/ShiftModal';
import HolidayRequestsDrawer from './components/HolidayRequestsDrawer';
import CopyWeekButton from './components/CopyWeekButton';
import ShiftTypeFilter, {
  ShiftTypeFilterHandle,
} from './components/ShiftTypeFilter';
import ShiftSettingsModal from './components/ShiftSettingsModal';
import GenerateScheduleButton from './components/GenerateScheduleButton';
import AddToDispatchButton from './components/AddToDispatchButton';
import ShiftsDashboard from './components/ShiftsDashboard';
import { syncWeekToUrl, weekFromParams } from './utils/weekUrl';

type View = 'planning' | 'dashboard';

dayjs.extend(isoWeek);

type Props = {
  shiftTypes: string[];
};

const Planning = ({ shiftTypes }: Props) => {
  const { t } = useTranslation();

  const [weekStart, setWeekStart] = useState<Dayjs>(
    () => weekFromParams(window.location.search) ?? dayjs().startOf('isoWeek'),
  );
  const [modalState, setModalState] = useState<ShiftModalState>(null);
  const [drawerOpen, setDrawerOpen] = useState(false);
  const [typeFilter, setTypeFilter] = useState<string[]>([]);
  const [view, setView] = useState<View>('planning');

  // Keep the URL (?year=&week=) in sync so weeks are shareable/deep-linkable
  useEffect(() => {
    syncWeekToUrl(weekStart);
  }, [weekStart]);

  const typeFilterRef = useRef<ShiftTypeFilterHandle>(null);
  useHotkey('Mod+F', () => typeFilterRef.current?.focus());

  const after = weekStart.format('YYYY-MM-DD');
  const before = weekStart.add(6, 'day').format('YYYY-MM-DD');

  const { data: shiftsData, isFetching } = useGetShiftsQuery({
    after,
    before,
  });
  const { data: weekHolidaysData } = useGetHolidayRequestsQuery({
    after,
    before,
    status: ['approved', 'pending'],
  });
  const { data: users } = useGetPlanningUsersQuery();
  const { data: shiftSettings } = useGetShiftSettingsQuery();
  const typeColors = shiftSettings?.typeColors;

  const { data: bankHolidaysData } = useGetBankHolidaysQuery({
    after,
    before,
  });
  const bankHolidays = useMemo(() => {
    const map: Record<string, string> = {};
    (bankHolidaysData?.holidays ?? []).forEach(h => {
      map[h.date] = h.name;
    });
    return map;
  }, [bankHolidaysData]);

  // All the requests around today, for the review drawer
  const reviewRange = useMemo(
    () => ({
      after: dayjs().subtract(1, 'month').format('YYYY-MM-DD'),
      before: dayjs().add(1, 'year').format('YYYY-MM-DD'),
    }),
    [],
  );
  const { data: reviewHolidaysData } = useGetHolidayRequestsQuery(reviewRange);

  const shifts = shiftsData?.['hydra:member'] ?? [];

  // Filtered shifts drive what the grid shows (cards + which rider rows appear);
  // the unfiltered `shifts` stay available to the modal for conflict detection
  const filteredShifts = useMemo(
    () =>
      typeFilter.length === 0
        ? shifts
        : shifts.filter(s => typeFilter.includes(s.type)),
    [shifts, typeFilter],
  );

  const weekHolidays = weekHolidaysData?.['hydra:member'] ?? [];
  const reviewHolidays = reviewHolidaysData?.['hydra:member'] ?? [];
  const pendingCount = reviewHolidays.filter(
    h => h.status === 'pending',
  ).length;

  const sortedUsers = useMemo(
    () =>
      [...(users ?? [])].sort((a, b) => a.username.localeCompare(b.username)),
    [users],
  );

  // The grid only shows users with a shift or holiday in the visible week,
  // plus the ones added manually (until they are removed again)
  const [addedUris, setAddedUris] = useState<Uri[]>([]);
  const [removedUris, setRemovedUris] = useState<Uri[]>([]);

  const shiftUserUris = useMemo(
    () =>
      new Set(
        filteredShifts.flatMap(s => s.assignments.map(a => a.user['@id'])),
      ),
    [filteredShifts],
  );
  const holidayUserUris = useMemo(
    () => new Set(weekHolidays.map(h => h.user['@id'])),
    [weekHolidays],
  );

  const visibleUsers = useMemo(
    () =>
      sortedUsers.filter(user => {
        const uri = user['@id'];
        if (shiftUserUris.has(uri)) {
          return true;
        }
        if (removedUris.includes(uri)) {
          return false;
        }
        return holidayUserUris.has(uri) || addedUris.includes(uri);
      }),
    [sortedUsers, shiftUserUris, holidayUserUris, addedUris, removedUris],
  );

  const addUser = (uri: Uri) => {
    setAddedUris(uris => [...uris, uri]);
    setRemovedUris(uris => uris.filter(u => u !== uri));
  };

  const removeUser = (uri: Uri) => {
    setAddedUris(uris => uris.filter(u => u !== uri));
    setRemovedUris(uris => [...uris, uri]);
  };

  return (
    <div className="shift-planning">
      <div className="shift-planning__toolbar">
        <Space>
          <Segmented
            value={view}
            onChange={value => setView(value as View)}
            options={[
              { label: t('SHIFT_PLANNING_VIEW_PLANNING'), value: 'planning' },
              { label: t('SHIFT_PLANNING_DASHBOARD'), value: 'dashboard' },
            ]}
          />
          {view === 'planning' && (
            <>
              <WeekNavigator value={weekStart} onChange={setWeekStart} />
              <ShiftTypeFilter
                ref={typeFilterRef}
                shiftTypes={shiftTypes}
                value={typeFilter}
                onChange={setTypeFilter}
                typeColors={typeColors}
              />
            </>
          )}
        </Space>
        {view === 'planning' && (
          <Space>
            <Badge count={pendingCount}>
              <Button
                icon={<CarryOutOutlined />}
                onClick={() => setDrawerOpen(true)}>
                {t('SHIFT_PLANNING_HOLIDAY_REQUESTS')}
              </Button>
            </Badge>
            <ShiftSettingsModal shiftTypes={shiftTypes} />
            <CopyWeekButton weekStart={weekStart} />
            <GenerateScheduleButton weekStart={weekStart} />
            <AddToDispatchButton weekStart={weekStart} />
            <Button
              type="primary"
              onClick={() => setModalState({ date: weekStart })}>
              {t('SHIFT_PLANNING_NEW_SHIFT')}
            </Button>
          </Space>
        )}
      </div>
      {view === 'dashboard' ? (
        <ShiftsDashboard />
      ) : (
        <Spin spinning={isFetching}>
          <PlanningGrid
            weekStart={weekStart}
            shifts={filteredShifts}
            holidayRequests={weekHolidays}
            users={visibleUsers}
            allUsers={sortedUsers}
            onCreate={(day: Dayjs, userUri?: Uri) =>
              setModalState({ date: day, userUri })
            }
            onEdit={(shift: Shift) => setModalState({ shift })}
            onAddUser={addUser}
            onRemoveUser={removeUser}
            typeColors={typeColors}
            bankHolidays={bankHolidays}
          />
        </Spin>
      )}
      <ShiftModal
        state={modalState}
        shiftTypes={shiftTypes}
        users={sortedUsers}
        holidayRequests={weekHolidays}
        shifts={shifts}
        typeColors={typeColors}
        onClose={() => setModalState(null)}
      />
      <HolidayRequestsDrawer
        open={drawerOpen}
        holidayRequests={reviewHolidays}
        onClose={() => setDrawerOpen(false)}
      />
    </div>
  );
};

export default ({ shiftTypes }: Props) => {
  return (
    <Provider store={store}>
      <Planning shiftTypes={shiftTypes} />
    </Provider>
  );
};
