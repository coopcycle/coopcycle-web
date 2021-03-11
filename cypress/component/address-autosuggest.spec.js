import React from 'react'
import { render } from 'react-dom'
import yaml from 'js-yaml'
import { mount } from '@cypress/react'

import AddressAutosuggest from '../../js/app/components/AddressAutosuggest'

describe('Address Autosuggest', () => {

  // Do *NOT* use a arrow function,
  // to have a reference to "this"
  beforeEach(function () {

    cy
      .readFile('cypress/fixtures/components/address-autosuggest.yml')
      .then((str) => {
        try {
          this.expectations = yaml.load(str, 'utf8')
        } catch (e) {
          cy.log(e)
        }
      })

    cy.window().then((win) => {
      win.sessionStorage.clear()
    })

  })

  it('search address (Algolia, es)', function () {

    mount(<AddressAutosuggest
      country="es"
      language="es"
      algolia={{
        appId: Cypress.env('ALGOLIA_PLACES_APP_ID'),
        apiKey: Cypress.env('ALGOLIA_PLACES_API_KEY'),
        aroundLatLng: '40.416775,-3.703790',
        addressTemplate: 'city'
      }} />)

    this.expectations.es.forEach(expectation => {

      cy.get('#cypress-root input[type="search"]')
        .clear()
        .type(expectation.search, { timeout: 5000, delay: 30 })

      cy.get('#cypress-root')
        .find('ul[role="listbox"] li', { timeout: 5000 })
        .invoke('text')
        .should((suggestions) => {
          expectation.expect.forEach((item) => {
            expect(suggestions).to.include(item)
          })
        })

    })

  })

  it('search address (gb)', function () {

    mount(<AddressAutosuggest
      country="gb"
      language="en" />)

    cy.get('#cypress-root input[type="search"]')
      .clear()
      .type('yo24', { timeout: 5000, delay: 30 })

    cy.get('#cypress-root')
      .find('ul[role="listbox"] li', { timeout: 5000 })
      .invoke('text')
      .should((suggestions) => {
        expect(suggestions).to.include('YO24 1AA')
      })

    cy.get('#cypress-root input[type="search"]')
      .type('4n', { timeout: 5000, delay: 30 })

    cy.get('#cypress-root')
      .find('ul[role="listbox"] li', { timeout: 5000 })
      .invoke('text')
      .should((suggestions) => {
        expect(suggestions).to.include('YO24 4ND')
      })

    cy.get('#cypress-root')
      .find('ul[role="listbox"] li', { timeout: 5000 })
      .contains('YO24 4ND')
      .click()

    cy.get('#cypress-root')
      .find('.address-autosuggest__addon')
      .should('have.text', 'YO24 4ND')

  })

  it.skip('search address (Geocode.Earth, fr)', function () {

    cy.intercept('/address-autosuggest.html', { fixture: 'components/address-autosuggest.html' })
    cy.visit('/address-autosuggest.html')

    cy.get('#app').then($el => {

      render(<AddressAutosuggest
        country="fr"
        language="fr"
        geocodeEarth={{
          apiKey: Cypress.env('GEOCODE_EARTH_API_KEY'),
          boundaryCircleLatlon: '48.856613,2.352222'
        }} />, $el[0])

      this.expectationsFr.forEach(expectation => {

        cy.get('#app input[type="search"]')
          .clear()
          .type(expectation.search, { timeout: 5000, delay: 30 })

        cy.get('#app')
          .find('ul[role="listbox"] li', { timeout: 5000 })
          .invoke('text')
          .should((suggestions) => {
            expectation.expect.forEach((item) => {
              expect(suggestions).to.include(item)
            })
          })

      })

    })

  })

})
