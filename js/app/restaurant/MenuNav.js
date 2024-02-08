import React, { useEffect, useRef, useState } from 'react'
import { Anchor } from 'antd'

const { Link } = Anchor

const sectionToLink = (section) => {
  return `#${encodeURI(section.name)}`
}

export default function MenuNav (props) {
  const [targetOffset, setTargetOffset] = useState(undefined)
  const [currentAnchor, setCurrentAnchor] = useState(undefined)

  const ref = useRef(null)
  const linkRef = useRef(null)

  const getCurrentAnchor = () => {
    return currentAnchor
  }

  const onChange = (link) => {
    if (link) {
      setCurrentAnchor(link)
    } else {
      if (props.sections.length > 0) {
        setCurrentAnchor(sectionToLink(props.sections[0]))
      }
    }
  }

  useEffect(() => {
    linkRef.current?.scrollIntoView(false)
    // a nicer variant; but doesn't work on Chrome for android yet (v121)
    // linkRef.current?.scrollIntoView({ behavior: 'smooth', block: 'center' })
  }, [currentAnchor])

  useEffect(() => {
    setTargetOffset(ref.current.clientHeight)
  })

  return (
    <div ref={ref}>
      <Anchor
        getCurrentAnchor={getCurrentAnchor}
        onChange={onChange}
        targetOffset={targetOffset}>
        {props.sections.map((section, index) => (
          <div key={`menu-nav-section-${index}`} ref={(instance) => {
            if (currentAnchor === sectionToLink(section)) {
              linkRef.current = instance
            }
          }}>
            <Link href={sectionToLink(section)} title={section.name}/>
          </div>
        ))}
      </Anchor>
    </div>
  )
}
