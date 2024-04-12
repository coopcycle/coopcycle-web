context('Checkout', () => {
  beforeEach(() => {

    cy.symfonyConsole('coopcycle:fixtures:load -f cypress/fixtures/checkout.yml')

    cy.window().then((win) => {
      win.sessionStorage.clear()
    })
  })

  it('order something at restaurant', () => {

    cy.intercept('POST', '/fr/restaurant/*/cart').as('postRestaurantCart')
    cy.intercept('POST', '/fr/restaurant/*/cart/product/*').as('postProduct')

    cy.visit('/fr/')

    cy.clickRestaurant(
      'Crazy Hamburger',
      /\/fr\/restaurant\/[0-9]+-crazy-hamburger/
    )

    cy.wait('@postRestaurantCart')

    cy.contains('Cheeseburger').click()

    cy.get('#CHEESEBURGER-options')
      .should('be.visible')

    // Make sure to use a precise selector, because 2 products have same options
    cy.get('#CHEESEBURGER-options input[value="HAMBURGER_ACCOMPANIMENT_FRENCH_FRIES"]')
      .check()
    cy.get('#CHEESEBURGER-options input[value="HAMBURGER_DRINK_COLA"]')
      .check()

    cy.get('#CHEESEBURGER-options input[value="HAMBURGER_ACCOMPANIMENT_FRENCH_FRIES"]').should('be.checked')
    cy.get('#CHEESEBURGER-options input[value="HAMBURGER_DRINK_COLA"]').should('be.checked')

    cy.get('#CHEESEBURGER-options button[type="submit"]')
      .should('not.be.disabled')
      .click()

    cy.wait('@postProduct')

    cy.get('.cart__items').invoke('text').should('match', /Cheeseburger/)

    cy.searchAddress(
      '.ReactModal__Content--enter-address',
      '91 rue de rivoli paris',
      '91 Rue de Rivoli, 75004 Paris, France'
    )

    cy.wait('@postRestaurantCart')

    cy.get('.cart [data-testid="cart.shippingAddress"]')
      .should('have.text', '91 Rue de Rivoli, 75004 Paris, France')

    cy.contains('Cheese Cake').click()

    cy.wait('@postProduct')

    cy.get('.cart__items').invoke('text').should('match', /Cheese Cake/)

    cy.get('form[name="cart"]').submit()

    cy.location('pathname').should('eq', '/login')

    cy.login('bob', '12345678')

    cy.location('pathname').should('eq', '/order/')

    cy.get('input[name="checkout_address[customer][fullName]"]').type('John Doe')

    cy.contains('Commander').click()

    cy.location('pathname').should('eq', '/order/payment')

    cy.get('form[name="checkout_payment"] input[type="text"]').type('John Doe')
    cy.enterCreditCard()

    cy.get('form[name="checkout_payment"]').submit()

    cy.location('pathname', { timeout: 30000 }).should('match', /\/order\/confirm\/[a-zA-Z0-9]+/)

    cy.get('#order-timeline').contains('Commande en attente de validation')
  })

  it('order something at restaurant with existing address', () => {

    cy.visit('/login')

    cy.get('[name="_username"]').type('jane')
    cy.get('[name="_password"]').type('12345678')
    cy.get('[name="_submit"]').click()

    cy.location('pathname').should('eq', '/fr/')

    cy.get('[data-search="address"] input[type="search"]')
      .type('1 rue de', { timeout: 5000 })

    cy.get('[data-search="address"]')
      .find('.react-autosuggest__suggestions-container')
      .find('.react-autosuggest__section-container')
      // There should be 2 sections
      .then(($sections) => {
        cy.wrap($sections).should('have.length', 2)
      })
      // The first section should contain saved addresses
      .then(($sections) => {
        cy.wrap($sections)
          .eq(0)
          .find('.react-autosuggest__section-title')
          .invoke('text')
          .should('eq', 'Adresses sauvegardées')
      })

    // Click on the first suggestion
    cy.get('[data-search="address"]')
      .find('.react-autosuggest__suggestions-container')
      .find('.react-autosuggest__section-container')
      .eq(0)
      .contains('1, Rue de Rivoli, Paris, France')
      .click()

    cy.location().then((loc) => {
      expect(loc.pathname).to.eq('/fr/restaurants')
      expect(loc.search).to.match(/\?geohash=[a-z0-9]+&address=[A-Za-z0-9%=]+/)
    })

    cy.contains('Crazy Hamburger').click()

    cy.get('.cart [data-testid="cart.shippingAddress"]')
      .should('have.text', '1, Rue de Rivoli, Paris, France')
  })

  it('homepage search with vague address', () => {

    cy.intercept('POST', '/fr/restaurant/*/cart').as('postRestaurantCart')
    cy.intercept('GET', '/search/geocode?address=**').as('geocodeAddress')

    cy.visit('/fr/')

    cy.get('[data-search="address"] input[type="search"]')
      .type('rue de rivoli paris', { timeout: 5000, delay: 30 })

    cy.get('[data-search="address"]')
      .find('ul[role="listbox"] li', { timeout: 5000 })
      .contains('Rue de Rivoli, 75004 Paris, France')
      .click()

    cy.location('pathname').should('match', /\/fr\/restaurants/)

    cy.contains('Crazy Hamburger').click()

    cy.location('pathname').should('match', /\/fr\/restaurant\/[0-9]+-crazy-hamburger/)

    cy.get('.cart [data-testid="cart.shippingAddress"]')
      .should('have.text', 'Rue de Rivoli, 75004 Paris, France')

    cy.wait('@geocodeAddress')

    cy.get('.ReactModal__Content--enter-address')
      .should('be.visible')

    cy.get('.ReactModal__Content--enter-address')
      .invoke('text')
      .should('match', /Cette adresse n'est pas assez précise/)

    cy.get('.ReactModal__Content--enter-address input[type="search"]')
      .type('91 rue de rivoli paris', { timeout: 5000, delay: 30 })

    cy.get('.ReactModal__Content--enter-address')
      .find('ul[role="listbox"] li', { timeout: 5000 })
      .contains('91 Rue de Rivoli, 75004 Paris, France')
      .click()

    cy.wait('@postRestaurantCart')

    cy.get('.cart [data-testid="cart.shippingAddress"]')
      .should('have.text', '91 Rue de Rivoli, 75004 Paris, France')
  })

  it.skip('order something at restaurant with existing address (via modal)', () => {

    cy.intercept('POST', '/fr/restaurant/*/cart').as('postRestaurantCart')
    cy.intercept('POST', '/fr/restaurant/*/cart/product/*').as('postProduct')
    cy.intercept('POST', '/fr/restaurant/*/cart/address').as('postCartAddress')

    cy.visit('/login')

    cy.get('[name="_username"]').type('jane')
    cy.get('[name="_password"]').type('12345678')
    cy.get('[name="_submit"]').click()

    cy.contains('Crazy Hamburger').click()

    cy.location('pathname').should('match', /\/fr\/restaurant\/[0-9]+-crazy-hamburger/)

    cy.wait('@postRestaurantCart')

    cy.contains('Cheeseburger').click()

    cy.get('#CHEESEBURGER-options')
      .should('be.visible')

    // Make sure to use a precise selector, because 2 products have same options
    cy.get('#CHEESEBURGER-options input[value="HAMBURGER_ACCOMPANIMENT_FRENCH_FRIES"]')
      .check()
    cy.get('#CHEESEBURGER-options input[value="HAMBURGER_DRINK_COLA"]')
      .check()

    cy.get('#CHEESEBURGER-options input[value="HAMBURGER_ACCOMPANIMENT_FRENCH_FRIES"]').should('be.checked')
    cy.get('#CHEESEBURGER-options input[value="HAMBURGER_DRINK_COLA"]').should('be.checked')

    cy.get('#CHEESEBURGER-options form [data-direction="up"]').click()
    cy.get('#CHEESEBURGER-options form [data-direction="up"]').click()

    cy.get('#CHEESEBURGER-options button[type="submit"]')
      .should('not.be.disabled')
      .click()

    cy.wait('@postProduct')

    cy.get('.cart__items').invoke('text').should('match', /Cheeseburger/)

    cy.get('.ReactModal__Content--enter-address')
      .should('be.visible')

    cy.get('.ReactModal__Content--enter-address input[type="search"]')
      .type('1 rue de', { timeout: 5000, delay: 30 })

    cy.get('.ReactModal__Content--enter-address')
      .find('.react-autosuggest__suggestions-container')
      .find('.react-autosuggest__section-container')
      // There should be 2 sections
      .then(($sections) => {
        cy.wrap($sections).should('have.length', 2)
      })
      // The first section should contain saved addresses
      .then(($sections) => {
        cy.wrap($sections)
          .eq(0)
          .find('.react-autosuggest__section-title')
          .invoke('text')
          .should('eq', 'Adresses sauvegardées')
      })

    // Click on the first suggestion
    cy.get('.ReactModal__Content--enter-address')
      .find('.react-autosuggest__suggestions-container')
      .find('.react-autosuggest__section-container')
      .eq(0)
      .contains('1, Rue de Rivoli, Paris, France')
      .click()

    cy.wait('@postCartAddress')

    cy.get('.cart__items').invoke('text').should('match', /Cheeseburger/)

    cy.get('form[name="cart"]').submit()

    cy.location('pathname').should('eq', '/order/')
  })

  it('order something at restaurant with deposit-refund enabled (as guest)', () => {

    cy.symfonyConsole('craue:setting:create --section="general" --name="guest_checkout_enabled" --value="1" --force')

    cy.intercept('POST', '/fr/restaurant/*/cart').as('postRestaurantCart')
    cy.intercept('POST', '/fr/restaurant/*/cart/product/*').as('postProduct')

    cy.visit('/fr/restaurants')

    cy.contains('Zero Waste Inc.').click()

    cy.location('pathname').should('match', /\/fr\/restaurant\/[0-9]+-zero-waste/)

    cy.wait('@postRestaurantCart')

    cy.contains('Salade au poulet').click()
    cy.wait('@postProduct')

    cy.get('.cart__items').invoke('text').should('match', /Salade au poulet/)

    cy.get('.ReactModal__Content--enter-address')
      .should('be.visible')

    cy.get('.ReactModal__Content--enter-address input[type="search"]')
      .type('91 rue de la roquette paris', { timeout: 5000, delay: 30 })

    cy.get('.ReactModal__Content--enter-address')
      .find('ul[role="listbox"] li', { timeout: 5000 })
      .contains('91 Rue de la Roquette, 75011 Paris, France')
      .click()

    cy.wait('@postRestaurantCart')

    cy.get('.cart [data-testid="cart.shippingAddress"]')
      .should('have.text', '91 Rue de la Roquette, 75011 Paris, France')

    cy.contains('Salade au poulet').click()
    cy.wait('@postProduct')

    cy.contains('Salade au poulet').click()
    cy.wait('@postProduct')

    // FIXME Use click instead of submit
    cy.get('form[name="cart"]').submit()

    cy.location('pathname').should('eq', '/order/')

    cy.get('.table-order-items tfoot tr:last-child td')
      .invoke('text')
      .invoke('trim')
      .should('match', /^18,00/)

    cy.get('#checkout_address_reusablePackagingEnabled')
      .should('be.visible')

    cy.get('#checkout_address_reusablePackagingEnabled')
      .closest('.alert')
      .invoke('text')
      .should('match', /Je veux des emballages réutilisables/)

    cy.get('#checkout_address_reusablePackagingEnabled').click()

    cy.location('pathname').should('eq', '/order/')

    cy.get('.table-order-items tfoot tr:last-child td')
      .invoke('text')
      .invoke('trim')
      .should('match', /^21,00/)
  })

  it('order something at restaurant with a tip (as guest)', () => {

    cy.symfonyConsole('craue:setting:create --section="general" --name="guest_checkout_enabled" --value="1" --force')

    cy.intercept('POST', '/fr/restaurant/*/cart').as('postRestaurantCart')
    cy.intercept('POST', '/fr/restaurant/*/cart/product/*').as('postProduct')
    cy.intercept('POST', '/order/').as('postOrder')
    cy.intercept('GET', '/search/geocode?address=**').as('geocodeAddress')

    cy.visit('/fr/')

    cy.contains('Crazy Hamburger').click()

    cy.location('pathname').should('match', /\/fr\/restaurant\/[0-9]+-crazy-hamburger/)

    cy.wait('@postRestaurantCart')

    cy.contains('Cheeseburger').click()

    cy.get('#CHEESEBURGER-options')
      .should('be.visible')

    // Make sure to use a precise selector, because 2 products have same options
    cy.get('#CHEESEBURGER-options input[value="HAMBURGER_ACCOMPANIMENT_FRENCH_FRIES"]')
      .check()
    cy.get('#CHEESEBURGER-options input[value="HAMBURGER_DRINK_COLA"]')
      .check()

    cy.get('#CHEESEBURGER-options input[value="HAMBURGER_ACCOMPANIMENT_FRENCH_FRIES"]').should('be.checked')
    cy.get('#CHEESEBURGER-options input[value="HAMBURGER_DRINK_COLA"]').should('be.checked')

    cy.get('#CHEESEBURGER-options button[type="submit"]')
      .should('not.be.disabled')
      .click()

    cy.wait('@postProduct')

    cy.get('.cart__items').invoke('text').should('match', /Cheeseburger/)

    cy.get('.ReactModal__Content--enter-address')
      .should('be.visible')

    cy.get('.ReactModal__Content--enter-address input[type="search"]')
      .type('91 rue de rivoli paris', { timeout: 5000, delay: 30 })

    cy.get('.ReactModal__Content--enter-address')
      .find('ul[role="listbox"] li', { timeout: 5000 })
      .contains('91 Rue de Rivoli, 75004 Paris, France')
      .click()

    cy.wait('@geocodeAddress')
    cy.wait('@postRestaurantCart')

    cy.get('.cart [data-testid="cart.shippingAddress"]')
      .should('have.text', '91 Rue de Rivoli, 75004 Paris, France')

    cy.contains('Cheese Cake').click()

    cy.wait('@postProduct')

    cy.get('.cart__items').invoke('text').should('match', /Cheese Cake/)

    // FIXME Use click instead of submit
    cy.get('form[name="cart"]').submit()

    cy.location('pathname').should('eq', '/order/')

    cy.get('.table-order-items tfoot tr:last-child td')
      .invoke('text')
      .invoke('trim')
      .should('match', /^20,00/)

    cy.get('#tip-incr').click()
    cy.wait('@postOrder')

    cy.get('.loadingoverlay', { timeout: 15000 }).should('not.exist')

    cy.get('.table-order-items tfoot tr:last-child td')
      .invoke('text')
      .invoke('trim')
      .should('match', /^21,00/)
  })

  it('order something at restaurant (as guest)', () => {

    cy.symfonyConsole('craue:setting:create --section="general" --name="guest_checkout_enabled" --value="1" --force')

    cy.intercept('POST', '/fr/restaurant/*/cart').as('postRestaurantCart')
    cy.intercept('POST', '/fr/restaurant/*/cart/product/*').as('postProduct')

    cy.visit('/fr/')

    cy.clickRestaurant(
      'Crazy Hamburger',
      /\/fr\/restaurant\/[0-9]+-crazy-hamburger/
    )

    cy.wait('@postRestaurantCart')

    cy.contains('Cheeseburger').click()

    cy.get('#CHEESEBURGER-options')
      .should('be.visible')

    // Make sure to use a precise selector, because 2 products have same options
    cy.get('#CHEESEBURGER-options input[value="HAMBURGER_ACCOMPANIMENT_FRENCH_FRIES"]')
      .check()
    cy.get('#CHEESEBURGER-options input[value="HAMBURGER_DRINK_COLA"]')
      .check()

    cy.get('#CHEESEBURGER-options input[value="HAMBURGER_ACCOMPANIMENT_FRENCH_FRIES"]').should('be.checked')
    cy.get('#CHEESEBURGER-options input[value="HAMBURGER_DRINK_COLA"]').should('be.checked')

    cy.get('#CHEESEBURGER-options button[type="submit"]')
      .should('not.be.disabled')
      .click()

    cy.wait('@postProduct')

    cy.get('.cart__items').invoke('text').should('match', /Cheeseburger/)

    cy.searchAddress(
      '.ReactModal__Content--enter-address',
      '91 rue de rivoli paris',
      '91 Rue de Rivoli, 75004 Paris, France'
    )

    cy.wait('@postRestaurantCart')

    cy.get('.cart [data-testid="cart.shippingAddress"]')
      .should('have.text', '91 Rue de Rivoli, 75004 Paris, France')

    cy.contains('Cheese Cake').click()

    cy.wait('@postProduct')

    cy.get('.cart__items').invoke('text').should('match', /Cheese Cake/)

    // FIXME Use click instead of submit
    cy.get('form[name="cart"]').submit()

    cy.location('pathname').should('eq', '/order/')

    cy.get('input[name="checkout_address[customer][email]"]').type('dev@coopcycle.org')
    cy.get('input[name="checkout_address[customer][fullName]"]').type('John Doe')
    cy.get('input[name="checkout_address[customer][phoneNumber]"]').type('0612345678')
    cy.get('input[name="checkout_address[customer][legal]"]').check()

    cy.contains('Commander').click()

    cy.location('pathname').should('eq', '/order/payment')

    cy.get('form[name="checkout_payment"] input[type="text"]').type('John Doe')
    cy.enterCreditCard()

    cy.get('form[name="checkout_payment"]').submit()

    cy.location('pathname').should('match', /\/order\/confirm\/[a-zA-Z0-9]+/)

    cy.get('#order-timeline').contains('Commande en attente de validation')
  })

  it('start ordering in one restaurant, then navigate to another restaurant', () => {

    cy.intercept('POST', '/fr/restaurant/*/cart').as('postRestaurantCart')
    cy.intercept('POST', '/fr/restaurant/*/cart/product/*').as('postProduct')

    cy.visit('/fr/')

    cy.clickRestaurant(
      'Crazy Hamburger',
      /\/fr\/restaurant\/[0-9]+-crazy-hamburger/
    )

    cy.wait('@postRestaurantCart')

    cy.contains('Cheese Cake').click()

    cy.wait('@postProduct')

    cy.get('.ReactModal__Content--enter-address').should('be.visible')
    cy.get('.cart__items').invoke('text').should('match', /Cheese Cake/)

    cy.get('.ReactModal__Content--enter-address .close').click()

    cy.visit('/fr/')

    cy.clickRestaurant(
      'Pizza Express',
      /\/fr\/restaurant\/[0-9]+-pizza-express/
    )

    cy.wait('@postRestaurantCart')

    cy.get('#cart .panel-body .cart .alert-warning').should('have.text', 'Votre panier est vide')

    cy.contains('Pizza Margherita').click()

    cy.wait('@postProduct')

    cy.get('.ReactModal__Content--restaurant').should('be.visible')
  })
})
