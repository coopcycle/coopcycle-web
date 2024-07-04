import React from 'react'
import yaml from 'js-yaml'

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

  it('search address (gb)', function () {

    cy.mount(<AddressAutosuggest
      country="gb"
      language="en" />)

    cy.get('[data-cy-root] input[type="search"]')
      .clear()
      .type('yo24', { timeout: 5000, delay: 30 })

    cy.get('[data-cy-root]')
      .find('ul[role="listbox"] li', { timeout: 5000 })
      .invoke('text')
      .should((suggestions) => {
        expect(suggestions).to.include('YO24 1AA')
      })

    cy.get('[data-cy-root] input[type="search"]')
      .type('4n', { timeout: 5000, delay: 30 })

    cy.get('[data-cy-root]')
      .find('ul[role="listbox"] li', { timeout: 5000 })
      .invoke('text')
      .should((suggestions) => {
        expect(suggestions).to.include('YO24 4ND')
      })

    cy.get('[data-cy-root]')
      .find('ul[role="listbox"] li', { timeout: 5000 })
      .contains('YO24 4ND')
      .click()

    cy.get('[data-cy-root]')
      .find('.address-autosuggest__addon')
      .should('have.text', 'YO24 4ND')

  })

  it('search address (Geocode.Earth, fr)', function () {

    console.log(Cypress.env())

    cy.mount(<AddressAutosuggest
      country="fr"
      language="fr"
      geocodeEarth={{
        apiKey: Cypress.env('GEOCODE_EARTH_API_KEY'),
        boundaryCircleLatlon: '48.856613,2.352222'
      }} />
    ).then(() => {

      this.expectations.fr.forEach(expectation => {

        cy.get('[data-cy-root] input[type="search"]')
          .clear()
          .type(expectation.search, { timeout: 5000, delay: 30 })

        cy.get('[data-cy-root]')
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
