export const getAdapter = (props, document) => {

  if (Object.prototype.hasOwnProperty.call(props, 'algolia')) {
    return 'algolia'
  }

  if (Object.prototype.hasOwnProperty.call(props, 'geocodeEarth')) {
    return 'geocode-earth'
  }

  if (Object.prototype.hasOwnProperty.call(props, 'locationIQ')) {
    return 'locationiq'
  }

  const el = document.getElementById('autocomplete-adapter')

  return (el && el.dataset.value) || 'algolia'
}

export const getAdapterOptions = (props, document) => {

  let adapterOptions = {
    algolia: {},
    'geocode-earth': {},
    locationiq: {},
  }
  if (Object.prototype.hasOwnProperty.call(props, 'algolia')) {
    adapterOptions.algolia = props.algolia
  } else if (Object.prototype.hasOwnProperty.call(props, 'geocodeEarth')) {
    adapterOptions['geocode-earth'] = props.geocodeEarth
  } else if (Object.prototype.hasOwnProperty.call(props, 'locationIQ')) {
    adapterOptions.locationiq = props.locationIQ
  } else {

    const algoliaEl      = document.getElementById('algolia-places')
    const geocodeEarthEl = document.getElementById('geocode-earth')
    const locationIQEl   = document.getElementById('locationiq')

    adapterOptions.algolia = (algoliaEl && { ...algoliaEl.dataset }) || {}
    adapterOptions['geocode-earth'] = (geocodeEarthEl && { ...geocodeEarthEl.dataset }) || {}
    adapterOptions.locationiq = (locationIQEl && { ...locationIQEl.dataset }) || {}
  }

  return adapterOptions
}
