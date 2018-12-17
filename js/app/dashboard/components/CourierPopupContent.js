import React, { Component } from 'react'
import TimeAgo from 'react-timeago'

import buildFormatter from 'react-timeago/lib/formatters/buildFormatter'
import esStrings from 'react-timeago/lib/language-strings/es'
import frStrings from 'react-timeago/lib/language-strings/fr'

const locale = $('html').attr('lang')

let formatter
switch (locale) {
case 'es':
  formatter = buildFormatter(esStrings)
  break
case 'fr':
  formatter = buildFormatter(frStrings)
  break
}

export default class extends Component {
  render() {

    const { username, lastSeen } = this.props

    return (
      <div className="text-center">
        <span>{ username }</span>
        <br />
        <TimeAgo date={ lastSeen.toDate() } formatter={ formatter } />
      </div>
    )
  }
}
