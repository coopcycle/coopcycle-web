import React from 'react'
import Modal from 'react-modal'

import AddressAutosuggest from '../../js/app/components/AddressAutosuggest'
import i18n from '../../js/app/i18n'

// Saved addresses are turned into suggestions without hitting any geocoding
// API, which lets us open the suggestions dropdown offline, by just focusing
// the input.
const savedAddresses = [
  {
    '@id': '/api/addresses/1',
    streetAddress: '91 Rue de Rivoli, Paris, France',
    geo: { latitude: 48.85863, longitude: 2.34429 },
  },
  {
    '@id': '/api/addresses/2',
    streetAddress: '12 Rue Marx Dormoy, Paris, France',
    geo: { latitude: 48.89093, longitude: 2.36015 },
  },
]

// A Pelias (Geocode Earth) autocomplete response. Stubbing it lets us render
// suggestions that do *not* come from the saved addresses, i.e a plain list
// instead of sections.
const autocompleteResponse = {
  features: [
    {
      geometry: { coordinates: [2.33145, 48.86877] },
      properties: {
        id: 'openstreetmap:address:node/1',
        name: '14 Rue de la Paix',
        postalcode: '75002',
        locality: 'Paris',
        country: 'France',
        label: '14 Rue de la Paix, Paris, France',
      },
    },
  ],
}

// An empty, but valid, MapLibre style. The basemap is not what we are testing
// here, and the tests should not depend on OpenFreeMap being reachable.
const emptyMapStyle = {
  version: 8,
  sources: {},
  layers: [],
}

const mountAutosuggest = (props = {}) => {
  cy.mount(
    <AddressAutosuggest
      country="fr"
      language="en"
      address=""
      addresses={savedAddresses}
      // Pick an adapter explicitly, as each of them has its own attribution
      geocodeEarth={{
        apiKey: 'not-used-here',
        boundaryCircleLatlon: '48.856613,2.352222',
      }}
      mapPickerEnabled={true}
      {...props} />
  )

  // Tell react-modal which element to hide from screen readers
  Modal.setAppElement('[data-cy-root]')
}

const openSuggestions = () => {
  cy.get('[data-cy-root] input[type="search"]').focus()
  cy.get('[data-cy-root] ul[role="listbox"] li').should(
    'have.length.at.least',
    1,
  )
}

describe('Address Autosuggest | map picker', () => {

  beforeEach(() => {
    // i18next detects the language from the browser, which differs between a
    // local run and CI. Pin it, so that we can assert on the labels.
    cy.wrap(i18n.changeLanguage('en'))

    cy.intercept('https://tiles.openfreemap.org/**', emptyMapStyle)
    cy.intercept('https://tile.openstreetmap.org/**', { statusCode: 204 })

    // Typing is debounced, so a request can be sent after the test that typed
    // is over. Stub it everywhere, to never reach the real API.
    cy.intercept('GET', 'https://api.geocode.earth/v1/autocomplete*', {
      statusCode: 200,
      body: autocompleteResponse,
    }).as('autocomplete')
  })

  it('renders the footer with the attribution and the map picker button', () => {

    mountAutosuggest()
    openSuggestions()

    // The attribution is a legal requirement of the geocoding providers,
    // it must be shown as soon as there are suggestions
    cy.get('[data-cy="address-autosuggest-footer"]')
      .should('be.visible')
      .and('contain.text', 'Geocode Earth')
      .and('contain.text', 'OpenStreetMap')

    cy.get('[data-cy="address-autosuggest-map-picker"]')
      .should('be.visible')
      .and('contain.text', 'Cannot find your address?')
  })

  // Regression test: the footer used to be rendered only when the suggestions
  // came as sections, which happens only when there are saved addresses. The
  // attribution was missing from every other search.
  it('renders the footer when the suggestions are not sectioned', () => {

    mountAutosuggest({ addresses: [] })

    cy.get('[data-cy-root] input[type="search"]').type('14 rue de la paix')

    cy.wait('@autocomplete')

    // A plain list of suggestions, not sections
    cy.get('[data-cy-root] ul[role="listbox"]').should('have.length', 1)
    cy.get('[data-cy-root] ul[role="listbox"] li').should('have.length', 1)

    cy.get('[data-cy="address-autosuggest-footer"]')
      .should('be.visible')
      .and('contain.text', 'Geocode Earth')

    cy.get('[data-cy="address-autosuggest-map-picker"]').should('be.visible')
  })

  it('does not render the map picker button when it is disabled', () => {

    mountAutosuggest({ mapPickerEnabled: false })
    openSuggestions()

    // The attribution stays, only the button goes away
    cy.get('[data-cy="address-autosuggest-footer"]')
      .should('be.visible')
      .and('contain.text', 'Geocode Earth')

    cy.get('[data-cy="address-autosuggest-map-picker"]').should('not.exist')
  })

  it('opens the map picker and closes the suggestions', () => {

    mountAutosuggest()
    openSuggestions()

    cy.get('[data-cy="address-autosuggest-map-picker"]').click()

    cy.get('[data-cy="map-picker-modal"]').should('be.visible')

    // The suggestions container has a higher z-index than the modal, it would
    // be rendered on top of it if it was left open
    cy.get('[data-cy-root] ul[role="listbox"]').should('not.exist')
    cy.get('[data-cy="address-autosuggest-footer"]').should('not.exist')
  })

  it('reverse geocodes the position selected on the map', () => {

    cy.intercept('GET', '/search/reverse*', {
      statusCode: 200,
      body: {
        address: '14 Rue de la Paix',
        locality: 'Paris',
        postalCode: '75002',
      },
    }).as('reverseGeocode')

    mountAutosuggest({ onAddressSelected: cy.stub().as('onAddressSelected') })
    openSuggestions()

    cy.get('[data-cy="address-autosuggest-map-picker"]').click()

    // The modal gets its size & position from the app's global stylesheet,
    // which component tests don't load, so the map overlaps the button here
    cy.get('[data-cy="map-picker-confirm"]').click({ force: true })

    cy.wait('@reverseGeocode')

    cy.get('@onAddressSelected').should(
      'have.been.calledWithMatch',
      '[MAP_PICKER]',
      Cypress.sinon.match({
        streetAddress: '14 Rue de la Paix',
        addressLocality: 'Paris',
        postalCode: '75002',
        provider: 'MAP_PICKER',
        isPrecise: true,
      }),
    )

    cy.get('[data-cy="map-picker-modal"]').should('not.exist')
  })
})
