import React, { Component } from 'react'
import TimeAgo from 'react-timeago'
import { makeIntlFormatter } from 'react-timeago/defaultFormatter'

const locale = $('html').attr('lang')

const intlFormatter = makeIntlFormatter({
  locale, // string
  // localeMatcher?: 'best fit', // 'lookup' | 'best fit',
  // numberingSystem?: 'latn' // Intl$NumberingSystem such as 'arab', 'deva', 'hebr' etc.
  // style?: 'long', // 'long' | 'short' | 'narrow',
  // numeric?: 'auto', //  'always' | 'auto', Using 'auto` will convert "1 day ago" to "yesterday" etc.
})

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
        <TimeAgo date={ lastSeen.toDate() } formatter={ intlFormatter } />
      </div>
    )
  }
}
