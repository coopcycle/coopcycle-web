import React from 'react'
import { render } from 'react-dom'
import openingHourIntervalToReadable from '../restaurant/parseOpeningHours.jsx'

class OpeningHoursDisplay extends React.Component {
  render () {
    return (
      <ul className="list-unstyled">
        { this.props.openingHours.map(function (item, index) {
          return ( <li key={ index }>{ openingHourIntervalToReadable(item) }</li> )
         })}
      </ul>
    )
  }
}

window.CoopCycle = window.CoopCycle || {}
window.CoopCycle.OpeningHoursParser = function(el, options) {
  render(<OpeningHoursDisplay openingHours={ options.openingHours } />, el)
}
