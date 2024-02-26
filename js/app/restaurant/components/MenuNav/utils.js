export const sectionToLink = (section) => {
  return `#${ encodeURI(section.name) }`
}

export const currentSection = (sections, currentAnchor) => {
  if (currentAnchor) {
    return sections.find((section) => sectionToLink(section) === currentAnchor)
  } else {
    return null
  }
}
