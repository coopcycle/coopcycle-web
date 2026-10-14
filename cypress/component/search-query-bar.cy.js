import React from 'react'

// import styles and common widgets (i18n included)
import common from '../../js/app/common'
import SearchQueryBar from '../../js/app/components/SearchQueryBar/SearchQueryBar'

// The owners a `loadOptions` field would fetch from the API - stubbed here,
// so these tests need neither a running server nor ORM fixtures.
const OWNERS = [
  'Les Invisibles',
  'Les Sakura',
  'Les Récupérables',
  'Le Lapin Noir',
]

const loadOwners = input =>
  Promise.resolve(
    OWNERS.filter(name =>
      name.toLowerCase().includes(input.toLowerCase()),
    ).map(name => ({ label: name, value: name })),
  )

const fields = [
  {
    key: 'state',
    label: 'State',
    type: 'enum',
    options: [
      { label: 'New', value: 'new' },
      { label: 'Cancelled', value: 'cancelled' },
    ],
  },
  {
    key: 'owner',
    label: 'Owner',
    type: 'async',
    multi: true,
    loadOptions: loadOwners,
  },
]

const INPUT = '[data-cy="search-query-bar-input"]'
const SUGGESTION = '[data-cy="search-query-bar-suggestion"]'
const PENDING_TAG = '[data-cy="search-query-bar-pending-tag"]'
const TAG = '[data-cy="search-query-bar-tag"]'
const EXCLUDE = '[data-cy="search-query-bar-exclude"]'

const mountBar = (props = {}) => {
  cy.mount(
    <SearchQueryBar
      fields={fields}
      onSearch={cy.stub().as('onSearch')}
      {...props}
    />,
  )
  return cy.get(INPUT)
}

const suggestion = label => cy.contains(SUGGESTION, label)

describe('SearchQueryBar', () => {
  describe('multiple selection', () => {
    it('ticking boxes updates the bar without running the search', () => {
      mountBar().type('owner:les')

      suggestion('Les Invisibles').find('.ant-checkbox-input').click()
      suggestion('Les Sakura').find('.ant-checkbox-input').click()

      // Both ticks show up as the filter being built...
      cy.get(PENDING_TAG)
        .should('contain', 'Les Invisibles')
        .and('contain', 'Les Sakura')
      // ...and the dropdown stays open, so more boxes can be ticked.
      cy.get(SUGGESTION).should('have.length.above', 1)
      // Running the search here would reload the page mid-selection.
      cy.get('@onSearch').should('not.have.been.called')
    })

    it('submits every ticked value on Enter', () => {
      mountBar().type('owner:les')

      suggestion('Les Invisibles').find('.ant-checkbox-input').click()
      suggestion('Les Sakura').find('.ant-checkbox-input').click()

      cy.get(INPUT).type('{enter}')

      cy.get('@onSearch').should(
        'have.been.calledOnceWith',
        'owner:("Les Invisibles" OR "Les Sakura")',
      )
      cy.get(TAG).should('contain', 'Les Invisibles')
      cy.get(PENDING_TAG).should('not.exist')
    })

    it('finishes the filter when the label is clicked', () => {
      mountBar().type('owner:les')

      suggestion('Les Sakura').contains('span', 'Les Sakura').click()

      // A single value collapses to the plain form, no "(...)" group.
      cy.get('@onSearch').should(
        'have.been.calledOnceWith',
        'owner:"Les Sakura"',
      )
    })

    it('adds the ticked values to the clicked one', () => {
      mountBar().type('owner:les')

      suggestion('Les Invisibles').find('.ant-checkbox-input').click()
      suggestion('Les Sakura').contains('span', 'Les Sakura').click()

      cy.get('@onSearch').should(
        'have.been.calledOnceWith',
        'owner:("Les Invisibles" OR "Les Sakura")',
      )
    })

    it('applies what is ticked when the bar loses focus', () => {
      mountBar().type('owner:les')

      suggestion('Les Invisibles').find('.ant-checkbox-input').click()

      cy.get(INPUT).blur()

      cy.get('@onSearch').should(
        'have.been.calledOnceWith',
        'owner:"Les Invisibles"',
      )
      cy.get(TAG).should('contain', 'Les Invisibles')
    })
  })

  describe('exclusion', () => {
    it('excludes a value without typing a "-"', () => {
      mountBar({ defaultValue: 'state:new' }).type('owner:les')

      suggestion('Les Invisibles').find(EXCLUDE).click()

      // The exclusion is a finished filter: it shows as a tag, the search
      // runs, and nothing of what was typed to find the value is left
      // behind (a half-typed "-owner:les" would read as if the click had
      // done nothing).
      cy.get(TAG)
        .contains('-')
        .parent()
        .should('contain', 'owner')
        .and('contain', 'Les Invisibles')
      cy.get(INPUT).should('have.value', '')
      cy.get('@onSearch').should(
        'have.been.calledOnceWith',
        'state:new -owner:"Les Invisibles"',
      )
    })

    it('drops the ticked values the exclusion replaces', () => {
      mountBar().type('owner:les')

      suggestion('Les Sakura').find('.ant-checkbox-input').click()
      suggestion('Les Invisibles').find(EXCLUDE).click()

      cy.get('@onSearch').should(
        'have.been.calledOnceWith',
        '-owner:"Les Invisibles"',
      )
      cy.get(PENDING_TAG).should('not.exist')
    })

    it('excludes a single-value field right away', () => {
      mountBar().type('state:')

      // The shortcut shows on the row under the pointer.
      suggestion('Cancelled').trigger('mouseover')
      suggestion('Cancelled').find(EXCLUDE).click()

      cy.get('@onSearch').should('have.been.calledOnceWith', '-state:cancelled')
      cy.get(TAG).should('contain', 'state').and('contain', 'cancelled')
    })

    it('strikes the row through while the shortcut is hovered', () => {
      mountBar().type('owner:les')

      const row = () => suggestion('Les Invisibles')

      row()
        .find('[data-cy="search-query-bar-suggestion-label"]')
        .should('have.css', 'text-decoration-line', 'none')

      row().find(EXCLUDE).trigger('mouseover')

      row()
        .find('[data-cy="search-query-bar-suggestion-label"]')
        .should('have.css', 'text-decoration-line', 'line-through')
    })
  })

  describe('values containing spaces', () => {
    it('keeps typing a value on space, instead of ending the filter', () => {
      mountBar().type('owner:Les Sak')

      // The space didn't split "owner:Les" off as a finished filter...
      cy.get(TAG).should('not.exist')
      cy.get(INPUT).should('have.value', 'owner:Les Sak')
      // ...and the whole value keeps narrowing the suggestions down.
      cy.get(SUGGESTION).should('have.length', 1)
      suggestion('Les Sakura').should('exist')
    })

    it('quotes a typed value containing spaces', () => {
      mountBar().type('owner:Les Zzz{enter}')

      cy.get('@onSearch').should(
        'have.been.calledOnceWith',
        'owner:"Les Zzz"',
      )
    })
  })
})
