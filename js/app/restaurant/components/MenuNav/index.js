import React, { useMemo, useState } from 'react'
import { Anchor } from 'antd'
import useSize from '@react-hook/size'

const { Link } = Anchor
import classNames from 'classnames'

import './menu-nav.scss'
import MoreMenu from './MoreMenu'
import { currentSection, elementId, sectionToLink } from './utils'

const paddingX = 24
const oneSymbol = 8
const maxMoreWidth = 160

const sectionElementId = (section) => `menu-nav-section-${ elementId(section) }`

const layoutSections = (sections, width) => {
  let displaySections = []

  let occupiedWidth = 0
  let isSpaceAvailable = true

  sections.forEach((section, index) => {
    if (isSpaceAvailable) {
      const el = document.getElementById(sectionElementId(section))

      let sectionWidth = paddingX + section.name.length * oneSymbol + paddingX
      if (el) {
        // use the actual width if it's available
        const width = Math.ceil(el.getBoundingClientRect().width)
        if (width > 0) {
          sectionWidth = width
        }
      }

      const isFirstItem = index === 0
      const isLastItem = index === sections.length - 1

      if (isFirstItem
        || (occupiedWidth + sectionWidth + maxMoreWidth <= width)
        || (isLastItem && occupiedWidth + sectionWidth <= width)) {
        occupiedWidth += sectionWidth
      } else {
        isSpaceAvailable = false
      }
    }

    displaySections.push({
      ...section,
      isVisible: isSpaceAvailable,
    })
  })

  return displaySections
}

export default function MenuNav(props) {
  const [ currentAnchor, setCurrentAnchor ] = useState(
    props.sections.length > 0 ? sectionToLink(props.sections[0]) : undefined)

  const rootRef = React.useRef(null)
  const [ width, height ] = useSize(rootRef)

  const displaySections = useMemo(() => layoutSections(props.sections, width),
    [ props.sections, width ])

  const getCurrentAnchor = () => currentAnchor

  const onChange = (link) => {
    if (link) {
      setCurrentAnchor(link)
    }
  }

  return (
    <Anchor
      getCurrentAnchor={ getCurrentAnchor }
      onChange={ onChange }
      targetOffset={ height }>
      <div className="custom-container pt-3 d-flex"
           ref={ rootRef }>
        { displaySections.map((section) => (
          <div
            key={ sectionElementId(section) }
            id={ sectionElementId(section) }
            className={ classNames(
              {
                'overflow-hidden': section.isVisible,
                'display-none': !section.isVisible,
              },
            ) }>
            <Link href={ sectionToLink(section) } title={ section.name } />
          </div>
        )) }
        <MoreMenu
          sections={ displaySections.filter((section) => !section.isVisible) }
          currentSection={ currentSection(props.sections, currentAnchor) }
          targetOffset={ height } />
      </div>
    </Anchor>
  )
}
