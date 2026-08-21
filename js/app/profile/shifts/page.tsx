import React from 'react';
import { Provider } from 'react-redux';
import { Col, Divider, Row } from 'antd';
import dayjs from 'dayjs';
import isoWeek from 'dayjs/plugin/isoWeek';
import { useTranslation } from 'react-i18next';
import { store } from './redux/store';
import MyShiftsWeek from './components/MyShiftsWeek';
import HolidayRequestForm from './components/HolidayRequestForm';
import MyHolidayRequestsList from './components/MyHolidayRequestsList';
import AvailabilityRuleForm from './components/AvailabilityRuleForm';
import MyAvailabilityRulesList from './components/MyAvailabilityRulesList';

dayjs.extend(isoWeek);

const MyShifts = () => {
  const { t } = useTranslation();

  return (
    <Row gutter={24}>
      <Col xs={24} md={14}>
        <h4>{t('SHIFT_PLANNING_MY_SHIFTS')}</h4>
        <MyShiftsWeek />
      </Col>
      <Col xs={24} md={10}>
        <h4>{t('SHIFT_PLANNING_REQUEST_HOLIDAY')}</h4>
        <HolidayRequestForm />
        <Divider />
        <h4>{t('SHIFT_PLANNING_MY_REQUESTS')}</h4>
        <MyHolidayRequestsList />
        <Divider />
        <h4>{t('SHIFT_PLANNING_AVAILABILITY_TITLE')}</h4>
        <p className="text-muted">{t('SHIFT_PLANNING_AVAILABILITY_HELP')}</p>
        <AvailabilityRuleForm />
        <MyAvailabilityRulesList />
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
