import React from 'react'
import { connect } from 'react-redux'

import LogisticsDashboard from './LogisticsDashboard'
import MarketplaceDashboard from './MarketplaceDashboard'
import ZeroWasteDashboard from './ZeroWasteDashboard'
import ProfitabilityDashboard from './ProfitabilityDashboard'
import Navbar from './Navbar'

const Dashboard = ({ view }) => {

  return (
    <React.Fragment>
      <Navbar />
      { view === 'marketplace'   && <MarketplaceDashboard /> }
      { view === 'logistics'     && <LogisticsDashboard /> }
      { view === 'zerowaste'     && <ZeroWasteDashboard /> }
      { view === 'profitability' && <ProfitabilityDashboard /> }
    </React.Fragment>
  )
}

function mapStateToProps(state) {

  return {
    view: state.view,
  }
}

export default connect(mapStateToProps)(Dashboard)
