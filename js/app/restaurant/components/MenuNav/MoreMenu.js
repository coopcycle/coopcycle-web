import { useTranslation } from 'react-i18next'
import { Dropdown } from 'antd'
import React from 'react'
import CustomLink from './CustomLink'
import { sectionToLink } from './utils'

export default function MoreMenu({ displaySections, currentSection }) {
  const { t } = useTranslation()

  let items = []
  let currentSectionInMoreMenu = undefined

  displaySections.filter((section) => !section.isVisible).
    forEach((section, index) => {
      if (currentSection.name === section.name) {
        currentSectionInMoreMenu = section
      } else {
        items.push(
          {
            key: `menu-nav-more-section-${ index }`,
            label: (
              <CustomLink
                title={ section.name }
                href={ sectionToLink(section) } />),
          },
        )
      }
    })

  if (items.length > 0) {
    return (
      <>
        <div className="ant-anchor-link ant-anchor-space">
        </div>
        <Dropdown
          trigger={ [ 'click' ] }
          menu={ { items: items } }
          placement="bottomRight"
          overlayClassName="restaurant-menu-nav-more">
          <CustomLink
            title={ currentSectionInMoreMenu?.name ??
              t('RESTAURANT_SECTIONS_MORE') }
            href="#"
            onClick={ (e) => e.preventDefault() }
            isActive={ Boolean(currentSectionInMoreMenu) } />
        </Dropdown>
      </>
    )
  } else {
    return null
  }
}
