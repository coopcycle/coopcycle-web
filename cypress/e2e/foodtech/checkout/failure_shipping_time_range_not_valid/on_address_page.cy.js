describe('Failed checkout; time range is not valid any more', () => {
  beforeEach(() => {
    cy.loadFixtures('checkout.yml')

    cy.setMockDateTime('2025-01-10 21:30:00')

    cy.login('bob', '12345678')

    cy.visit('/fr/')

    cy.intercept('POST', '/fr/restaurant/*/cart').as('postRestaurantCart1')

    cy.clickRestaurant(
      'Crazy Hamburger',
      /\/fr\/restaurant\/[0-9]+-crazy-hamburger/,
    )

    //FIXME: why do we send two requests?
    cy.wait(['@postRestaurantCart1', '@postRestaurantCart1'])

    cy.intercept('POST', '/fr/restaurant/*/cart/product/*').as('postProduct1')

    cy.addProduct('Cheeseburger', '#CHEESEBURGER_crazy_hamburger-options', 2, [
      'HAMBURGER_ACCOMPANIMENT_FRENCH_FRIES_crazy_hamburger',
      'HAMBURGER_DRINK_COLA_crazy_hamburger',
    ])

    cy.wait('@postProduct1', { timeout: 5000 })

    cy.get('.cart__items')
      .invoke('text')
      .should('match', /Cheeseburger/)

    cy.intercept('POST', '/fr/restaurant/*/cart').as('postRestaurantCart2')
    cy.searchAddressUsingAddressModal(
      '.ReactModal__Content--enter-address',
      '91 rue de rivoli paris',
      /^91,? Rue de Rivoli,? 75001,? Paris,? France/i,
    )
    cy.wait('@postRestaurantCart2')

    cy.get('[data-testid="cart.shippingAddress"]:visible')
      .invoke('text')
      .should('match', /^91,? Rue de Rivoli,? 75001,? Paris,? France/i)

    cy.get('[data-testid="cart.time"]:visible a').click()

    cy.intercept('POST', '/fr/restaurant/*/cart').as('postRestaurantCart3')
    cy.get('[data-testid="cart.time.submit"]:visible').click()
    cy.wait('@postRestaurantCart3')
  })

  afterEach(() => {
    cy.resetMockDateTime()
  })

  context(
    'time range expired while the customer was on the address page',
    () => {
      it('show an error message (address page)', () => {
        cy.get('.order-button:visible').click()

        cy.urlmatch(/\/order\/$/)

        cy.get('input[name="checkout_address[customer][fullName]"]').type(
          'John Doe',
        )

        //simulate expired time range by closing the restaurant
        cy.closeRestaurantForToday('resto_1', 'resto_1')

        cy.contains('Commander').click()

        cy.get('form[name="checkout_address"]', { timeout: 10000 }).contains(
          " L'horaire de livraison n'est plus disponible",
        )
      })
    },
  )
})
