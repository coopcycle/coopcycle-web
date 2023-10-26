import React, { useState, useEffect } from 'react'
import { render } from 'react-dom'
import ReactMarkdown from 'react-markdown'
import changelogParser from '@release-notes/changelog-parser'
import axios from 'axios'
import moment from 'moment'
import Cookies from 'js-cookie'
import { compare } from 'compare-versions'

import { Badge, Popover } from 'antd'

const getLatestVersion = releases => releases[0].version

const getNewReleasesCount = (releases, lastViewedVersion) => {

  const newReleases = releases.reduce((accumulator, release) => {
    if (compare(release.version, lastViewedVersion, '>')) {
      accumulator.push(release)
    }

    return accumulator
  }, [])

  return newReleases.length
}

const Release = ({ release }) => {

  return (
    <li>
      <h5>{ `${release.version} – ${moment(release.date).format('LL')}` }</h5>
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

const ChangelogContent = ({ releases }) => {

  return (
    <div>
      <ul className="list-unstyled">
        { releases.map((release) => (
          <Release key={ release.version } release={ release } />
        )) }
      </ul>
      <div className="text-right">
        <a target="_blank" rel="noreferrer" href="https://github.com/coopcycle/coopcycle-web/blob/master/CHANGELOG.md">View all</a>
      </div>
    </div>
  )
}

const zeroStyle = {
  backgroundColor: 'transparent',
  color: 'inherit',
  boxShadow: '0 0 0 1px #d9d9d9 inset'
}

const Changelog = ({ releases, newReleasesCount }) => {

  const [ visible, setVisible ] = useState(false)
  const [ releasesCount, setReleasesCount ] = useState(newReleasesCount)

  useEffect(() => {
    if (visible) {
      const latestVersion = getLatestVersion(releases)
      Cookies.set('__changelog_latest', latestVersion)
      setTimeout(() => setReleasesCount(0), 800)
    }
  }, [ visible ])

  const badgeProps = releasesCount === 0 ? { style: zeroStyle } : {}

  return (
    <Popover
      content={ <ChangelogContent releases={ releases } /> }
      title="Changelog"
      trigger="click"
      open={ visible }
      onOpenChange={ value => setVisible(value) }
    >
      <a href="#">
        <Badge count={ releasesCount } showZero { ...badgeProps } title={ `${releasesCount} new release(s)` } />
      </a>
    </Popover>
  )
}

export default function(el) {

  const lastViewedVersion = Cookies.get('__changelog_latest')

  axios.get('/CHANGELOG.md').then(response => {
    const { releases } = changelogParser.parse(response.data)

    // Show only the last 5 releases
    releases.splice(5)

    const latestVersion = getLatestVersion(releases)

    let newReleasesCount = 0
    if (lastViewedVersion !== latestVersion) {
      if (!lastViewedVersion) {
        newReleasesCount = releases.length
      } else {
        newReleasesCount = getNewReleasesCount(releases, lastViewedVersion)
      }
    }

    render(<Changelog releases={ releases } newReleasesCount={ newReleasesCount } />, el)
  })
}
