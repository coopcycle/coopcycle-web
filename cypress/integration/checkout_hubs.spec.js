context('Checkout (hubs)', () => {
  beforeEach(() => {

    cy.symfonyConsole('coopcycle:fixtures:load -f cypress/fixtures/checkout_hubs.yml')

    cy.window().then((win) => {
      win.sessionStorage.clear()
    })
  })

  it.skip('order something at hub', () => {

    cy.intercept('POST', '/fr/restaurant/*/cart').as('postRestaurantCart')
    cy.intercept('POST', '/fr/restaurant/*/cart/product/*').as('postProduct')
    cy.intercept('POST', '/fr/restaurant/*/cart/address').as('postCartAddress')

    cy.visit('/fr/')

    cy.clickRestaurant(
      'Crazy Hamburger',
      /\/fr\/restaurant\/[0-9]+-crazy-hamburger/
    )
    cy.wait('@postRestaurantCart')

    cy.contains('Cheese Cake').click()
    cy.wait('@postProduct')

    cy.get('.ReactModal__Content--enter-address').should('be.visible')
    cy.get('.ReactModal__Content--enter-address button.close').click()

    cy.get('.cart__items').invoke('text').should('match', /Cheese Cake/)

    cy.visit('/fr/')

    cy.clickRestaurant(
      'Pizza Express',
      /\/fr\/restaurant\/[0-9]+-pizza-express/
    )
    cy.wait('@postRestaurantCart')

    // The cart should *NOT* be empty,
    // because both restaurants are in the same hub
    // it should contain the product previously added
    cy.get('.cart__items').invoke('text').should('match', /Cheese Cake/)

    cy.searchAddress(
      '.ReactModal__Content--enter-address',
      '91 rue de rivoli paris',
      '91 Rue de Rivoli, 75004 Paris, France'
    )

    cy.wait('@postCartAddress')

    cy.contains('Pizza Margherita').click()
    cy.wait('@postProduct')
    cy.get('.cart__items').invoke('text').should('match', /Pizza Margherita/)
  })
})
