import React from 'react'
import { connect } from 'react-redux'

import BestRestaurants from './BestRestaurants'
import AverageCart from './AverageCart'
import OrderCountPerDayOfWeek from './OrderCountPerDayOfWeek'
import OrderCountPerHourRange from './OrderCountPerHourRange'
import Navbar from './Navbar'

const Dashboard = ({ cubejsApi, dateRange }) => {

  return (
    <div>
      <Navbar />
      <div style={{ minHeight: '240px' }}>
        <BestRestaurants cubejsApi={ cubejsApi } dateRange={ dateRange } />
      </div>
      <div style={{ minHeight: '240px' }}>
        <AverageCart cubejsApi={ cubejsApi } dateRange={ dateRange } />
      </div>
      <div style={{ minHeight: '240px' }}>
        <OrderCountPerDayOfWeek cubejsApi={ cubejsApi } dateRange={ dateRange } />
      </div>
      <div style={{ minHeight: '240px' }}>
        <OrderCountPerHourRange cubejsApi={ cubejsApi } dateRange={ dateRange } />
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
