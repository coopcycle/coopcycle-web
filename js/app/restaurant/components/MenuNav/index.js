import React, { useMemo, useState } from 'react'
import { Anchor } from 'antd'
import useSize from '@react-hook/size'

const { Link } = Anchor
import classNames from 'classnames'

import './menu-nav.scss'
import MoreMenu from './MoreMenu'
import { currentSection, sectionToLink } from './utils'

const paddingX = 24
const oneSymbol = 8
const maxMoreWidth = 160
const moreWidth = paddingX + maxMoreWidth + paddingX

const layoutSections = (sections, width) => {
  let displaySections = []

  let occupiedWidth = 0
  let isSpaceAvailable = true

  sections.forEach((section, index) => {
    if (isSpaceAvailable) {
      const sectionWidth = paddingX + section.name.length * oneSymbol + paddingX

      const isFirstItem = index === 0
      const isLastItem = index === sections.length - 1

      if (isFirstItem
        || (occupiedWidth + sectionWidth + moreWidth <= width)
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
    <div id="restaurant-menu-nav"
         className="restaurant-menu-nav"
         ref={ rootRef }>
      <Anchor
        getCurrentAnchor={ getCurrentAnchor }
        onChange={ onChange }
        targetOffset={ height }>
        { displaySections.map((section, index) => (
          <div
            key={ `menu-nav-section-${ index }` }
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
          displaySections={ displaySections }
          currentSection={ currentSection(props.sections, currentAnchor) } />
      </Anchor>
    </div>
  )
}
