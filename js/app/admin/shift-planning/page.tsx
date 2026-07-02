import React, { useMemo, useState } from 'react';
import { Provider } from 'react-redux';
import { Badge, Button, Space, Spin } from 'antd';
import { CarryOutOutlined } from '@ant-design/icons';
import dayjs, { Dayjs } from 'dayjs';
import isoWeek from 'dayjs/plugin/isoWeek';
import { useTranslation } from 'react-i18next';
import { TopNav } from '../../components/TopNav';
import { store } from './redux/store';
import {
  useGetHolidayRequestsQuery,
  useGetPlanningUsersQuery,
  useGetShiftsQuery,
} from '../../api/slice';
import { Shift, Uri } from '../../api/types';
import WeekNavigator from './components/WeekNavigator';
import PlanningGrid from './components/PlanningGrid';
import ShiftModal, { ShiftModalState } from './components/ShiftModal';
import HolidayRequestsDrawer from './components/HolidayRequestsDrawer';
import CopyWeekButton from './components/CopyWeekButton';

dayjs.extend(isoWeek);

type Props = {
  shiftTypes: string[];
};

const Planning = ({ shiftTypes }: Props) => {
  const { t } = useTranslation();

  const [weekStart, setWeekStart] = useState<Dayjs>(() =>
    dayjs().startOf('isoWeek'),
  );
  const [modalState, setModalState] = useState<ShiftModalState>(null);
  const [drawerOpen, setDrawerOpen] = useState(false);

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

  return (
    <div className="shift-planning">
      <TopNav>{t('SHIFT_PLANNING_TITLE')}</TopNav>
      <div className="shift-planning__toolbar">
        <WeekNavigator value={weekStart} onChange={setWeekStart} />
        <Space>
          <Badge count={pendingCount}>
            <Button
              icon={<CarryOutOutlined />}
              onClick={() => setDrawerOpen(true)}>
              {t('SHIFT_PLANNING_HOLIDAY_REQUESTS')}
            </Button>
          </Badge>
          <CopyWeekButton weekStart={weekStart} />
          <Button
            type="primary"
            onClick={() => setModalState({ date: weekStart })}>
            {t('SHIFT_PLANNING_NEW_SHIFT')}
          </Button>
        </Space>
      </div>
      <Spin spinning={isFetching}>
        <PlanningGrid
          weekStart={weekStart}
          shifts={shifts}
          holidayRequests={weekHolidays}
          users={sortedUsers}
          onCreate={(day: Dayjs, userUri?: Uri) =>
            setModalState({ date: day, userUri })
          }
          onEdit={(shift: Shift) => setModalState({ shift })}
        />
      </Spin>
      <ShiftModal
        state={modalState}
        shiftTypes={shiftTypes}
        users={sortedUsers}
        holidayRequests={weekHolidays}
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
