import React from 'react';
import { DatePicker } from 'antd';
import { useTranslation } from 'react-i18next';
import { Moment } from 'moment';

type Props = {
  initialDateRange?: Moment[] | null;
  setDateRange: (range: Moment[]) => void;
};

// Whether the range exactly spans a single full calendar month, i.e. what
// the simple "select month" picker can express
function isFullMonthRange(range: Moment[]): boolean {
  return (
    range[0].isSame(range[0].clone().startOf('month'), 'day') &&
    range[1].isSame(range[1].clone().endOf('month'), 'day') &&
    range[0].isSame(range[1], 'month')
  );
}

export default function RangePicker({
  initialDateRange,
  setDateRange,
}: Props) {
  const [isComplexPicker, setIsComplexPicker] = React.useState(
    () => !!initialDateRange && !isFullMonthRange(initialDateRange),
  );

  const { t } = useTranslation();

  const title = isComplexPicker
    ? t('ADMIN_ORDERS_TO_INVOICE_FILTER_RANGE_SIMPLE')
    : t('ADMIN_ORDERS_TO_INVOICE_FILTER_RANGE_COMPLEX');

  return (
    <div className="d-flex flex-column">
      {t('ADMIN_ORDERS_TO_INVOICE_FILTER_RANGE')}
      {isComplexPicker ? (
        <DatePicker.RangePicker
          // antd's RangePicker types this as a Dayjs range; this app uses moment
          defaultValue={
            (initialDateRange
              ? [initialDateRange[0], initialDateRange[1]]
              : undefined) as unknown as React.ComponentProps<
              typeof DatePicker.RangePicker
            >['defaultValue']
          }
          onChange={dates => {
            setDateRange(dates);
          }}
        />
      ) : (
        <DatePicker
          picker="month"
          defaultValue={initialDateRange ? initialDateRange[0] : undefined}
          onChange={date => {
            const range = [
              date.clone().local().startOf('month'),
              date.clone().local().endOf('month'),
            ];
            setDateRange(range);
          }}
        />
      )}
      <a
        className="text-secondary"
        title={title}
        data-testid="invoicing.toggleRangePicker"
        onClick={() => setIsComplexPicker(!isComplexPicker)}>
        {title}
      </a>
    </div>
  );
}
