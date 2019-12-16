import React from 'react'
import { render } from 'react-dom'
import openingHourIntervalToReadable from '../restaurant/parseOpeningHours'

class OpeningHoursDisplay extends React.Component {
  render () {
    return (
      <ul className="list-unstyled">
        { this.props.openingHours.map((item, index) => {
          return ( <li key={ index }>{ openingHourIntervalToReadable(item, this.props.locale) }</li> )
        })}
      </ul>
    )
  }
}

export default function(el, options) {
  render(<OpeningHoursDisplay openingHours={ options.openingHours } locale={ options.locale } />, el)
}
