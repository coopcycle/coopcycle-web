// https://www.algolia.com/doc/api-client/getting-started/upgrade-guides/javascript/#the-initplaces-method
import algoliasearch from 'algoliasearch/lite'
import { shuffle } from '@algolia/client-common'

export const places = (appId = '', apiKey = '', options) => {
  const placesClient = algoliasearch(appId, apiKey, {
    hosts: [{ url: 'places-dsn.algolia.net' }].concat(
      shuffle([
        { url: 'places-1.algolia.net' },
        { url: 'places-2.algolia.net' },
        { url: 'places-3.algolia.net' }
      ])
    ),
    ...options
  });
  return (data, requestOptions) => {
    return placesClient.transporter.read(
      {
        method: 'POST',
        path: '1/places/query',
        data,
        cacheable: true
      },
      requestOptions
    );
  };
}

export const initSearch = () => {
  const el = document.getElementById('algolia-places')
  if (el) {
    return places(el.dataset.appId, el.dataset.apiKey)
  }
}

// https://community.algolia.com/places/api-clients.html#json-answer
export const hitToAddress = (hit, value = '') => {

  const streetAddress = value ?
    value : `${hit.locale_names[0]}, ${hit.city[0]}, ${hit.country}`

  return {
    // FIXME Use "geo" key everywhere, and remove
    latitude: hit._geoloc.lat,
    longitude: hit._geoloc.lng,
    geo: {
      latitude: hit._geoloc.lat,
      longitude: hit._geoloc.lng,
    },
    addressCountry: hit.country || '',
    addressLocality: hit.city[0] || '',
    addressRegion: hit.administrative[0] || '',
    postalCode: hit.postcode[0] || '',
    streetAddress,
    // https://community.algolia.com/places/examples.html#using-_rankinginfo
    // By default, Places only offers precision up to the street level,
    // which means that all the house numbers of a street will have the same geolocation.
    // However, Places offers house level precision in France
    isPrecise: Object.prototype.hasOwnProperty.call(hit._rankingInfo, 'roadNumberPrecision'),
    needsGeocoding: Object.prototype.hasOwnProperty.call(hit._rankingInfo, 'roadNumberPrecision') && hit._rankingInfo.roadNumberPrecision === 'centroid',
  }
}
