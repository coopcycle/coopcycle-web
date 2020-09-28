context('Checkout', () => {
  beforeEach(() => {

    const prefix = Cypress.env('COMMAND_PREFIX')

    let cmd = 'bin/console coopcycle:fixtures:load -f cypress/fixtures/checkout.yml --env test'
    if (prefix) {
      cmd = `${prefix} ${cmd}`
    }

    cy.exec(cmd)

    cy.window().then((win) => {
      win.sessionStorage.clear()
    })
  })

  it('order something at restaurant', () => {

    cy.server()
    cy.route('POST', '/fr/restaurant/*-crazy-hamburger').as('postRestaurant')
    cy.route('POST', '/fr/restaurant/*/cart/product/*').as('postProduct')

    cy.visit('/fr/')

    cy.contains('Crazy Hamburger').click()

    cy.location('pathname').should('match', /\/fr\/restaurant\/[0-9]+-crazy-hamburger/)

    cy.wait('@postRestaurant')

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
      .contains('91 Rue de Rivoli, Paris, France')
      .click()

    cy.wait('@postRestaurant')

    cy.get('.cart .address-autosuggest__container input[type="search"]')
      .should('have.value', '91 Rue de Rivoli, Paris, France')

    cy.contains('Cheese Cake').click()

    cy.wait('@postProduct')

    cy.get('.cart__items').invoke('text').should('match', /Cheese Cake/)

    cy.get('form[name="cart"]').submit()

    cy.location('pathname').should('eq', '/login')

    cy.get('[name="_username"]').type('bob')
    cy.get('[name="_password"]').type('12345678')
    cy.get('[name="_submit"]').click()

    cy.location('pathname').should('eq', '/order/')

    cy.contains('Commander').click()

    cy.location('pathname').should('eq', '/order/payment')

    cy.get('form[name="checkout_payment"] input[type="text"]').type('John Doe')

    const expDate = Cypress.moment().add(6, 'month').format('MMYY')

    // @see https://github.com/cypress-io/cypress/issues/136
    cy.get('.StripeElement iframe')
        .then(function ($iframe) {

            const $body = $iframe.contents().find('body')

            cy
              .wrap($body)
              .find('input[name="cardnumber"]')
              .type('4242424242424242')

            cy
              .wrap($body)
              .find('input[name="exp-date"]')
              .type(expDate)

            cy
              .wrap($body)
              .find('input[name="cvc"]')
              .type('123')
        })

    cy.get('form[name="checkout_payment"]').submit()

    cy.location('pathname').should('match', /\/profile\/orders\/[0-9]+/)
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

    cy.get('.cart .address-autosuggest__container input[type="search"]')
      .should('have.value', '1, Rue de Rivoli, Paris, France')
  })

  it('homepage search with vague address', () => {

    cy.server()
    cy.route('POST', '/fr/restaurant/*-crazy-hamburger').as('postRestaurant')

    cy.visit('/fr/')

    cy.get('[data-search="address"] input[type="search"]')
      .type('rue de rivoli paris', { timeout: 5000, delay: 30 })

    cy.get('[data-search="address"]')
      .find('ul[role="listbox"] li', { timeout: 5000 })
      .contains('Rue de Rivoli, Paris, France')
      .click()

    cy.location('pathname').should('match', /\/fr\/restaurants/)

    cy.contains('Crazy Hamburger').click()

    cy.location('pathname').should('match', /\/fr\/restaurant\/[0-9]+-crazy-hamburger/)

    cy.get('.cart .address-autosuggest__container input[type="search"]')
      .should('have.value', 'Rue de Rivoli, Paris, France')

    cy.get('.ReactModal__Content--enter-address')
      .should('be.visible')

    cy.get('.ReactModal__Content--enter-address')
      .invoke('text')
      .should('match', /Cette adresse n'est pas assez précise/)

    cy.get('.ReactModal__Content--enter-address input[type="search"]')
      .type('91 rue de rivoli paris', { timeout: 5000, delay: 30 })

    cy.get('.ReactModal__Content--enter-address')
      .find('ul[role="listbox"] li', { timeout: 5000 })
      .contains('91 Rue de Rivoli, Paris, France')
      .click()

    cy.wait('@postRestaurant')

    cy.get('.cart .address-autosuggest__container input[type="search"]')
      .should('have.value', '91 Rue de Rivoli, Paris, France')
  })

  it.skip('order something at restaurant with existing address (via modal)', () => {

    cy.server()
    cy.route('POST', '/fr/restaurant/*-crazy-hamburger').as('postRestaurant')
    cy.route('POST', '/fr/restaurant/*/cart/product/*').as('postProduct')
    cy.route('POST', '/fr/restaurant/*/cart/address').as('postCartAddress')

    cy.visit('/login')

    cy.get('[name="_username"]').type('jane')
    cy.get('[name="_password"]').type('12345678')
    cy.get('[name="_submit"]').click()

    cy.contains('Crazy Hamburger').click()

    cy.location('pathname').should('match', /\/fr\/restaurant\/[0-9]+-crazy-hamburger/)

    cy.wait('@postRestaurant')

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

  it('order something at restaurant with deposit-refund enabled', () => {

    cy.server()
    cy.route('POST', '/fr/restaurant/*-zero-waste-inc').as('postRestaurant')
    cy.route('POST', '/fr/restaurant/*/cart/product/*').as('postProduct')

    cy.visit('/fr/restaurants')

    cy.contains('Zero Waste Inc.').click()

    cy.location('pathname').should('match', /\/fr\/restaurant\/[0-9]+-zero-waste/)

    cy.wait('@postRestaurant')

    cy.contains('Salade au poulet').click()
    cy.wait('@postProduct')

    cy.get('.cart__items').invoke('text').should('match', /Salade au poulet/)

    cy.get('.ReactModal__Content--enter-address')
      .should('be.visible')

    cy.get('.ReactModal__Content--enter-address input[type="search"]')
      .type('91 rue de la roquette paris', { timeout: 5000, delay: 30 })

    cy.get('.ReactModal__Content--enter-address')
      .find('ul[role="listbox"] li', { timeout: 5000 })
      .contains('91 Rue de la Roquette, Paris, France')
      .click()

    cy.wait('@postRestaurant')

    cy.get('.cart .address-autosuggest__container input[type="search"]')
      .should('have.value', '91 Rue de la Roquette, Paris, France')

    cy.contains('Salade au poulet').click()
    cy.wait('@postProduct')

    cy.contains('Salade au poulet').click()
    cy.wait('@postProduct')

    cy.get('form[name="cart"]').submit()

    cy.location('pathname').should('eq', '/login')

    cy.get('[name="_username"]').type('bob')
    cy.get('[name="_password"]').type('12345678')
    cy.get('[name="_submit"]').click()

    cy.location('pathname').should('eq', '/order/')

    cy.get('.table-order-items tfoot tr:last-child td ')
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

    // TODO
    // In this case, maybe we should reload the page?

    cy.location('pathname').should('eq', '/order/payment')

    cy.get('.table-order-items tfoot tr:last-child td ')
      .invoke('text')
      .invoke('trim')
      .should('match', /^21,00/)
  })
})
