import React from 'react'
import { connect } from 'react-redux'

import BestRestaurants from './BestRestaurants'
import AverageCart from './AverageCart'
import OrderCountPerDayOfWeek from './OrderCountPerDayOfWeek'
import OrderCountPerHourRange from './OrderCountPerHourRange'
import OrderCountPerZone from './OrderCountPerZone'
import Navbar from './Navbar'
import ChartPanel from './ChartPanel'

const Dashboard = ({ cubejsApi, dateRange }) => {

  return (
    <div>
      <Navbar />
      <div className="metrics-grid">
        <ChartPanel title="Best restaurants">
          <BestRestaurants cubejsApi={ cubejsApi } dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title="Average order total">
          <AverageCart cubejsApi={ cubejsApi } dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title="Number of orders per day of week">
          <OrderCountPerDayOfWeek cubejsApi={ cubejsApi } dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title="Number of orders per hour range">
          <OrderCountPerHourRange cubejsApi={ cubejsApi } dateRange={ dateRange } />
        </ChartPanel>
        <ChartPanel title="Number of orders per zone">
          <OrderCountPerZone cubejsApi={ cubejsApi } dateRange={ dateRange } />
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
