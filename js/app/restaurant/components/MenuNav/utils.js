// https://developer.mozilla.org/en-US/docs/Web/JavaScript/Reference/Global_Objects/encodeURIComponent#encoding_for_rfc3986
function encodeRFC3986URIComponent(str) {
  return encodeURIComponent(str).replace(
    /[!'()*]/g,
    (c) => `%${c.charCodeAt(0).toString(16).toUpperCase()}`,
  );
}

export const elementId = (section) => {
  return encodeRFC3986URIComponent(section.name)
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
