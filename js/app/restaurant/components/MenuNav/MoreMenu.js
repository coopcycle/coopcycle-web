import React, { useEffect, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Dropdown } from 'antd'

import CustomLink from './CustomLink'
import { elementId, sectionToLink } from './utils'

function MoreMenuItem({ section, targetOffset, onClick }) {
  const handleClick = (ev) => {
    ev.preventDefault()

    const elId = elementId(section)
    if (elId) {
      const el = document.getElementById(elId)

      el?.scrollIntoView(true)
      window.scrollBy(0, -1 * targetOffset) // 'Anchor' height

      // a nicer variant; but doesn't work on Chrome for android yet (v121)
      // el?.scrollIntoView({ behavior: 'smooth', block: 'start' })
    }

    onClick?.()
  }

  return (
    <CustomLink
      title={ section.name }
      href={ sectionToLink(section) }
      onClick={ handleClick } />
  )
}

export default function MoreMenu({ sections, currentSection, targetOffset }) {
  const [ isOpen, setIsOpen ] = useState(false)
  const { t } = useTranslation()

  let items = []
  let currentSectionInMoreMenu = undefined

  sections.forEach((section, index) => {
    if (currentSection.name === section.name) {
      currentSectionInMoreMenu = section
    } else {
      items.push(
        {
          key: `menu-nav-more-section-${ index }`,
          label: (
            <MoreMenuItem
              section={ section }
              targetOffset={ targetOffset }
              onClick={ () => setIsOpen(false) } />),
        },
      )
    }
  })

  useEffect(() => {
    // use similar to ReactModal approach to prevent body in the background from scrolling
    if (isOpen) {
      document.body.classList.add('body--no-scroll')
    } else {
      document.body.classList.remove('body--no-scroll')
    }
  }, [ isOpen ])

  if (items.length > 0) {
    return (
      <>
        <div className="ant-anchor-link ant-anchor-space">
        </div>
        <Dropdown
          menu={ { items: items } }
          overlayClassName="more-overlay"
          trigger={ [ 'click' ] }
          placement="bottomRight"
          autoAdjustOverflow={ false }
          onOpenChange={ (open) => setIsOpen(open) }>
          <div className="more-trigger">
            <CustomLink
              title={ currentSectionInMoreMenu?.name ??
                t('RESTAURANT_SECTIONS_MORE') }
              href="#"
              onClick={ (ev) => ev.preventDefault() }
              isActive={ Boolean(currentSectionInMoreMenu) }
              rightIcon={ isOpen ? 'fa-chevron-up' : 'fa-chevron-down' } />
          </div>
        </Dropdown>
      </>
    )
  } else {
    return null
  }
}
