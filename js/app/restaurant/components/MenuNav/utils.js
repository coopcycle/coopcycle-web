export const elementId = (section) => {
  return encodeURI(section.name)
}

export const sectionToLink = (section) => {
  return `#${ elementId(section) }`
}

export const currentSection = (sections, currentAnchor) => {
  if (currentAnchor) {
    return sections.find((section) => sectionToLink(section) === currentAnchor)
  } else {
    return null
  }
}
