context('Checkout (hubs)', () => {
  beforeEach(() => {

    cy.symfonyConsole('coopcycle:fixtures:load -f cypress/fixtures/checkout_hubs.yml')

    cy.window().then((win) => {
      win.sessionStorage.clear()
    })
  })

  it('order something at hub', () => {

    cy.server()
    cy.route('POST', '/fr/restaurant/*-crazy-hamburger').as('postCrazyHamburger')
    cy.route('POST', '/fr/restaurant/*-pizza-express').as('postPizzaExpress')
    cy.route('POST', '/fr/restaurant/*/cart/product/*').as('postProduct')

    cy.visit('/fr/')

    cy.clickRestaurant(
      'Crazy Hamburger',
      /\/fr\/restaurant\/[0-9]+-crazy-hamburger/
    )
    cy.wait('@postCrazyHamburger')

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
    cy.wait('@postPizzaExpress')

    // The cart should *NOT* be empty,
    // because both restaurants are in the same hub
    // it should contain the product previously added
    cy.get('.cart__items').invoke('text').should('match', /Cheese Cake/)

    cy.searchAddress(
      '.ReactModal__Content--enter-address',
      '91 rue de rivoli paris',
      '91 Rue de Rivoli, 75004 Paris, France'
    )

    cy.wait('@postPizzaExpress')

    cy.contains('Pizza Margherita').click()
    cy.wait('@postProduct')
    cy.get('.cart__items').invoke('text').should('match', /Pizza Margherita/)
  })
})
