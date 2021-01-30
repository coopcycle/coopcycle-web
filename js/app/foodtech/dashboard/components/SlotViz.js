import React from 'react'
import { connect } from 'react-redux'
import Moment from 'moment'
import { extendMoment } from 'moment-range'
import _ from 'lodash'
import chroma from 'chroma-js'
import Tooltip from 'antd/lib/tooltip'

import { selectHoursRanges, selectOrdersByHourRange } from '../redux/selectors'

const moment = extendMoment(Moment)

const chromaScale = chroma
  .scale([ '#f1c40f', '#e67e22', '#e74c3c' ])
  .classes(10)

const Slot = ({ range, count, percentage, index }) => {

  return (
    <Tooltip title={ count }>
      <div className="FoodtechDashboard__SlotViz__Slot" style={{ backgroundColor: count === 0 ? '#2ecc71' : chromaScale(percentage).hex() }}>
        { index > 0 && (
          <span className="FoodtechDashboard__SlotViz__Slot__Start">{ range.start.format('HH:mm') }</span>
        ) }
      </div>
    </Tooltip>
  )
}

const SlotViz = ({ hoursRanges, ordersByHourRange }) => {
  return (
    <React.Fragment>
      { hoursRanges.map((range, index) => {

        const o = _.find(ordersByHourRange, obhr => range === obhr.range)

        return (<Slot key={ `slot-${index}` }
          range={ moment.rangeFromISOString(range) }
          count={ (o && o.count) || 0 }
          percentage={ (o && o.percentage) || 0 }
          index={ index } />
        )
      })}
    </React.Fragment>
  )
}

function mapStateToProps(state) {
  return {
    hoursRanges: selectHoursRanges(state),
    ordersByHourRange: selectOrdersByHourRange(state)
  }
}

export default connect(mapStateToProps)(SlotViz)
