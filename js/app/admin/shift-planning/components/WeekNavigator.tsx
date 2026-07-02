import React from 'react';
import { Button, DatePicker, Space } from 'antd';
import { LeftOutlined, RightOutlined } from '@ant-design/icons';
import { Dayjs } from 'dayjs';
import dayjs from 'dayjs';
import isoWeek from 'dayjs/plugin/isoWeek';
import weekOfYear from 'dayjs/plugin/weekOfYear';
import weekYear from 'dayjs/plugin/weekYear';
import advancedFormat from 'dayjs/plugin/advancedFormat';
import { useTranslation } from 'react-i18next';

dayjs.extend(isoWeek);
dayjs.extend(weekOfYear);
dayjs.extend(weekYear);
dayjs.extend(advancedFormat);

type Props = {
  value: Dayjs;
  onChange: (weekStart: Dayjs) => void;
};

export default function WeekNavigator({ value, onChange }: Props) {
  const { t } = useTranslation();

  return (
    <Space>
      <Button
        icon={<LeftOutlined />}
        onClick={() => onChange(value.subtract(7, 'day'))}
      />
      <DatePicker
        picker="week"
        allowClear={false}
        value={value}
        onChange={d => d && onChange(d.startOf('isoWeek'))}
      />
      <Button
        icon={<RightOutlined />}
        onClick={() => onChange(value.add(7, 'day'))}
      />
      <Button onClick={() => onChange(dayjs().startOf('isoWeek'))}>
        {t('SHIFT_PLANNING_THIS_WEEK')}
      </Button>
    </Space>
  );
}
