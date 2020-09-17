import React from 'react'
import { render } from 'react-dom'
import openingHourIntervalToReadable from '../restaurant/parseOpeningHours'
import compactOpeningHours from '../restaurant/compactOpeningHours'

class OpeningHoursDisplay extends React.Component {
  render () {
    return (
      <ul className="list-unstyled">
        { this.props.openingHours.map((item, index) => {
          return ( <li key={ index }>{ openingHourIntervalToReadable(item, this.props.locale, this.props.behavior) }</li> )
        })}
      </ul>
    )
  }
}

export default function(el, options) {

  // Optimization when there is a lot of slots
  // @see https://github.com/coopcycle/coopcycle-web/issues/1488
  const openingHours = (options.behavior === 'time_slot') ?
    compactOpeningHours(options.openingHours) : options.openingHours;

  render(<OpeningHoursDisplay openingHours={ openingHours } locale={ options.locale } behavior={ options.behavior } />, el)
}
