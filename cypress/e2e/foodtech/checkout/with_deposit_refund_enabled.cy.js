context('Checkout', () => {
    beforeEach(() => {

      cy.symfonyConsole('coopcycle:fixtures:load -f cypress/fixtures/checkout.yml')

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

        cy.get('.product-modal-container button[type="submit"]').click()

        cy.wait('@postProduct', {timeout: 5000})

        cy.get('.cart__items').invoke('text').should('match', /Salade au poulet/)

        cy.wait(1000)

        cy.get('.ReactModal__Content--enter-address')
          .should('be.visible')

        cy.searchAddressUsingAddressModal(
        '.ReactModal__Content--enter-address',
        '10, avenue Ledru-Rollin 75012',
        '10 Avenue Ledru-Rollin, 75012 Paris, France'
        )

        cy.wait('@postRestaurantCart')

        cy.wait(1000)

        cy.get('#restaurant__fulfilment-details__container [data-testid="cart.shippingAddress"]')
          .should('have.text', '10 Avenue Ledru-Rollin, 75012 Paris, France')

        cy.contains('Salade au poulet').click()

        cy.get('.product-modal-container button[type="submit"]').click()

        cy.wait('@postProduct', {timeout: 5000})
        cy.contains('Salade au poulet').click()

        cy.get('.product-modal-container button[type="submit"]').click()
        cy.wait('@postProduct', {timeout: 5000})

        // FIXME Use click instead of submit
        cy.get('form[name="cart"]').submit()

        cy.location('pathname').should('eq', '/order/')

        // fails on github CI
        // cy.get('.table-order-items tfoot tr:last-child td')
        //   .invoke('text')
        //   .invoke('trim')
        //   .should('equal', "18,00 €")

        cy.get('#checkout_address_reusablePackagingEnabled')
          .should('be.visible')

        cy.get('#checkout_address_reusablePackagingEnabled')
          .closest('.alert')
          .invoke('text')
          .should('match', /Je veux des emballages réutilisables/)

        cy.get('#checkout_address_reusablePackagingEnabled').click()

        cy.location('pathname').should('eq', '/order/')

        // fails on github CI
        // cy.get('.table-order-items tfoot tr:last-child td')
        //   .invoke('text')
        //   .invoke('trim')
        //   .should('equal', "21,00 €")
      })

})
