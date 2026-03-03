import React from 'react'
import { connect } from 'react-redux'
import { useTranslation } from 'react-i18next'

import BestRestaurants from './BestRestaurants'
import AverageCart from './AverageCart'
import OrderCountPerDayOfWeek from './OrderCountPerDayOfWeek'
import OrderCountPerHourRange from './OrderCountPerHourRange'
import OrderCountPerZone from './OrderCountPerZone'
import OrderCountPerPaymentMethod from './OrderCountPerPaymentMethod'
import ChartPanel from './ChartPanel'

const Dashboard = ({ dateRange }) => {
  const { t } = useTranslation()

  return (
    <div>
      <div className="metrics-grid">
        <ChartPanel title={t('METRICS.BEST_RESTAURANTS')}>
          <BestRestaurants dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title={t('METRICS.AVERAGE_ORDER_TOTAL')}>
          <AverageCart dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title={t('METRICS.ORDERS_PER_DAY_OF_WEEK')}>
          <OrderCountPerDayOfWeek dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title={t('METRICS.ORDERS_PER_HOUR_RANGE')}>
          <OrderCountPerHourRange dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title={t('METRICS.ORDERS_PER_ZONE')}>
          <OrderCountPerZone dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title={t('METRICS.ORDERS_PER_PAYMENT_METHOD')}>
          <OrderCountPerPaymentMethod dateRange={ dateRange } />
        </ChartPanel>
      </div>
    </div>
  )
}

function mapStateToProps(state) {

  return {
    dateRange: state.dateRange,
  }
}

export default connect(mapStateToProps)(Dashboard)
