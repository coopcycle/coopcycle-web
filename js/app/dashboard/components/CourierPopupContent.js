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

  constructor (props) {
    super(props)
    this.state = {
      lastSeen: this.props.lastSeen
    }
  }

  updateLastSeen(lastSeen) {
    this.setState({ lastSeen })
  }

  render() {

    const { username } = this.props
    const { lastSeen } = this.state

    return (
      <div className="text-center">
        <strong>{ username }</strong>
        <br />
        <TimeAgo date={ lastSeen.toDate() } formatter={ formatter } />
      </div>
    )
  }
}
