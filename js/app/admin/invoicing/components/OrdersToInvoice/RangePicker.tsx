import React from 'react'
import { DatePicker } from 'antd'
import { useTranslation } from 'react-i18next'

export default function RangePicker({ setDateRange }) {
  const [isComplexPicker, setIsComplexPicker] = React.useState(false)

  const { t } = useTranslation()

  const title = isComplexPicker
    ? t('ADMIN_ORDERS_TO_INVOICE_FILTER_RANGE_SIMPLE')
    : t('ADMIN_ORDERS_TO_INVOICE_FILTER_RANGE_COMPLEX')

  return (
    <div className="d-flex flex-column">
      {t('ADMIN_ORDERS_TO_INVOICE_FILTER_RANGE')}
      {isComplexPicker ? (
        <DatePicker.RangePicker
          onChange={dates => {
            setDateRange(dates)
          }}
        />
      ) : (
        <DatePicker
          picker="month"
          onChange={date => {
            const range = [
              date.clone().local().startOf('month'),
              date.clone().local().endOf('month'),
            ]
            setDateRange(range)
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
  )
}
