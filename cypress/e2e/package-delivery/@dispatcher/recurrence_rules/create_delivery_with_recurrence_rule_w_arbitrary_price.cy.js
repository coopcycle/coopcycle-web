describe('Delivery with recurrence rule (role: admin)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup([
      'ORM/user_admin.yml',
      'ORM/tags.yml',
      'ORM/store_basic.yml',
    ])
    cy.login('admin', '12345678')
  })

  it('create delivery order and a recurrence rule with arbitrary price', function () {
    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une livraison')
      .click()

    // Create delivery page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries\/new$/)

    // Pickup
    cy.betaChooseSavedAddressAtPosition(0, 1)

    // Dropoff
    cy.betaChooseSavedAddressAtPosition(1, 2)

    cy.get(`[name="tasks[1].weight"]`).clear()
    cy.get(`[name="tasks[1].weight"]`).type(2.5)

    cy.get('[name="delivery.override_price"]').check()
    cy.get('[name="variantName"]').type('Test product')
    cy.get('#variantPriceVAT').type('72')

    cy.get('[data-testid="recurrence__container"]').find('a').click()
    cy.chooseDaysOfTheWeek([5, 6])
    cy.get('[data-testid=save]').click()

    cy.get('button[type="submit"]').click()

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

    cy.get('[data-testid="order_item"]')
      .find('[data-testid="total"]')
      .contains('€72.00')

    cy.get('[data-testid=delivery-itinerary]')
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery-itinerary]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')

    cy.get('a[href*="recurrence-rules"]').click()

    // Recurrence rule page
    cy.urlmatch(/\/admin\/stores\/[0-9]+\/recurrence-rules\/[0-9]+$/)
    cy.get('[data-tax="included"]').contains('72,00 €')
    cy.get('#delivery_form__recurrence__container').contains(
      'chaque semaine le vendredi, samedi',
    )

    cy.go('back')

    cy.get('[data-testid="order-edit"]').click()

    // Edit Delivery page
    cy.urlmatch(/\/admin\/deliveries\/[0-9]+$/)

    cy.get('[data-testid="recurrence__container"]').should('not.exist')
  })
})
