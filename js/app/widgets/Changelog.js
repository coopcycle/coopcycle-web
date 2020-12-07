import React, { useState } from 'react'
import { render } from 'react-dom'
import ReactMarkdown from 'react-markdown'
import changelogParser from '@release-notes/changelog-parser'
import axios from 'axios'
import moment from 'moment'

import { Badge, Popover } from 'antd'

const Release = ({ release }) => {

  return (
    <li>
      <h5>{ moment(release.date).format('LL') }</h5>
      <div>
        { release.added.map((content, index) => (
          <ReactMarkdown key={ `added-${index}` }>{ content }</ReactMarkdown>
        )) }
        { release.changed.map((content, index) => (
          <ReactMarkdown key={ `changed-${index}` }>{ content }</ReactMarkdown>
        )) }
        { release.deprecated.map((content, index) => (
          <ReactMarkdown key={ `deprecated-${index}` }>{ content }</ReactMarkdown>
        )) }
        { release.fixed.map((content, index) => (
          <ReactMarkdown key={ `fixed-${index}` }>{ content }</ReactMarkdown>
        )) }
        { release.removed.map((content, index) => (
          <ReactMarkdown key={ `removed-${index}` }>{ content }</ReactMarkdown>
        )) }
      </div>
    </li>
  )
}

const ChangelogContent = ({ releaseNotes }) => {

  return (
    <ul className="list-unstyled">
      { releaseNotes.releases.map((release) => (
        <Release key={ release.version } release={ release } />
      )) }
    </ul>
  )
}

const Changelog = ({ releaseNotes }) => {

  const [ visible, setVisible ] = useState(false)

  return (
    <Popover
      content={ <ChangelogContent releaseNotes={ releaseNotes } /> }
      title="Changelog"
      trigger="click"
      visible={ visible }
      onVisibleChange={ value => setVisible(value) }
    >
      <a href="#">
        <Badge count={ releaseNotes.releases.length } />
      </a>
    </Popover>
  )
}

export default function(el) {
  axios.get('/CHANGELOG.md').then(response => {
    const releaseNotes = changelogParser.parse(response.data)
    render(<Changelog releaseNotes={ releaseNotes } />, el)
  })
}
