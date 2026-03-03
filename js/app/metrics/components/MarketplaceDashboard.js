import React from 'react'
import { connect } from 'react-redux'

import BestRestaurants from './BestRestaurants'
import AverageCart from './AverageCart'
import OrderCountPerDayOfWeek from './OrderCountPerDayOfWeek'
import OrderCountPerHourRange from './OrderCountPerHourRange'
import OrderCountPerZone from './OrderCountPerZone'
import OrderCountPerPaymentMethod from './OrderCountPerPaymentMethod'
import ChartPanel from './ChartPanel'

const Dashboard = ({ dateRange }) => {

  return (
    <div>
      <div className="metrics-grid">
        <ChartPanel title="Best restaurants">
          <BestRestaurants dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title="Average order total">
          <AverageCart dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title="Number of orders per day of week">
          <OrderCountPerDayOfWeek dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title="Number of orders per hour range">
          <OrderCountPerHourRange dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title="Number of orders per zone">
          <OrderCountPerZone dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title="Number of orders per payment method">
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
