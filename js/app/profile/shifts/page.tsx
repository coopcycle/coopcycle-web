import React from 'react';
import { Provider } from 'react-redux';
import { Col, Divider, Row } from 'antd';
import dayjs from 'dayjs';
import isoWeek from 'dayjs/plugin/isoWeek';
import { useTranslation } from 'react-i18next';
import { store } from './redux/store';
import CalendarSyncButton from './components/CalendarSyncButton';
import MyShiftsWeek from './components/MyShiftsWeek';
import HolidayRequestForm from './components/HolidayRequestForm';
import MyHolidayRequestsList from './components/MyHolidayRequestsList';

dayjs.extend(isoWeek);

const MyShifts = () => {
  const { t } = useTranslation();

  return (
    <Row gutter={24}>
      <Col xs={24} md={14}>
        <div className="d-flex align-items-center justify-content-between">
          <h4>{t('SHIFT_PLANNING_MY_SHIFTS')}</h4>
          <CalendarSyncButton />
        </div>
        <MyShiftsWeek />
      </Col>
      <Col xs={24} md={10}>
        <h4>{t('SHIFT_PLANNING_REQUEST_HOLIDAY')}</h4>
        <HolidayRequestForm />
        <Divider />
        <h4>{t('SHIFT_PLANNING_MY_REQUESTS')}</h4>
        <MyHolidayRequestsList />
      </Col>
    </Row>
  );
};

export default () => {
  return (
    <Provider store={store}>
      <MyShifts />
    </Provider>
  );
};
