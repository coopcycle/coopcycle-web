import React, { useState, useEffect } from 'react'
import { createRoot } from 'react-dom/client'
import ReactMarkdown from 'react-markdown'
import changelogParser from '@release-notes/changelog-parser'
import axios from 'axios'
import moment from 'moment'
import Cookies from 'js-cookie'
import { compare } from 'compare-versions'
import { Tag } from 'antd'

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

// Render Markdown without a wrapping <p> tag
// https://github.com/remarkjs/react-markdown/issues/42
const UnwrappedMarkdown = (props) => {
  const { children, ...otherProps } = props

  return (
    <ReactMarkdown components={{
      p: React.Fragment,
    }} { ...otherProps }>{ children }</ReactMarkdown>
  )
}

const ReleaseSection = ({ section, type, color }) => {

  if (section.length === 0) {
    return null
  }

  return (
    <div>
      <div className="mb-2">
        <Tag color={ color }>{ type }</Tag>
      </div>
      <ul className="list-unstyled mb-2">
      { section.map((content, index) => (
        <li key={ `${type}-${index}` }>
          <UnwrappedMarkdown>{ content }</UnwrappedMarkdown>
        </li>
      )) }
      </ul>
    </div>
  )
}

const Release = ({ release }) => {

  return (
    <li>
      <h5 className="font-weight-bold">{ `${release.version} – ${moment(release.date).format('LL')}` }</h5>
      <div>
        <ReleaseSection type="added"      color="green"   section={ release.added } />
        <ReleaseSection type="changed"    color="blue"  section={ release.changed } />
        <ReleaseSection type="deprecated" color="orange" section={ release.deprecated } />
        <ReleaseSection type="fixed"      color="purple" section={ release.fixed } />
        <ReleaseSection type="removed"    color="red"    section={ release.removed } />
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
        <span className="ml-2">Whatʼs new?</span>
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

    createRoot(el).render(<Changelog releases={ releases } newReleasesCount={ newReleasesCount } />)
  })
}
