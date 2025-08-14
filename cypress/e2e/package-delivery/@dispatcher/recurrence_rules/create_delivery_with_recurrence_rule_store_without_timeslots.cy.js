describe('Delivery with recurrence rule (role: dispatcher)', () => {
  beforeEach(() => {
    cy.loadFixtures('ORM/stores_legacy.yml')
    cy.setMockDateTime('2025-04-23 8:30:00')
    cy.login('dispatcher', 'dispatcher')
  })

  afterEach(() => {
    cy.resetMockDateTime()
  })

  describe('store without time slots', function () {
    it('create delivery order and a recurrence rule', function () {
      cy.visit('/admin/stores')

      cy.get('[data-testid=store_Acme_without_time_slots__list_item]')
        .find('.dropdown-toggle')
        .click()

      cy.get('[data-testid=store_Acme_without_time_slots__list_item]')
        .contains('Créer une nouvelle commande')
        .click()

      // Create delivery page
      cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries\/new$/)

      // Pickup
      cy.betaChooseSavedAddressAtPosition(0, 1)

      //Set pickup time range to 10:10 - 11:20 manually
      cy.get('[data-testid="form-task-0"]').within(() => {
        cy.antdSelect('.ant-select[data-testid="select-after"]', '10:10')
        cy.antdSelect('.ant-select[data-testid="select-before"]', '11:20')
      })

      cy.betaEnterCommentAtPosition(0, 'Pickup comments')

      // Dropoff
      cy.betaChooseSavedAddressAtPosition(1, 2)

      //Set pickup time range to 11:30 - 12:40 manually
      cy.get('[data-testid="form-task-1"]').within(() => {
        cy.antdSelect('.ant-select[data-testid="select-after"]', '11:30')
        cy.antdSelect('.ant-select[data-testid="select-before"]', '12:40')
      })

      cy.betaEnterWeightAtPosition(1, 2.5)

      cy.betaEnterCommentAtPosition(1, 'Dropoff comments')

      cy.get('[data-testid="tax-included"]').contains('4,99 €')

      cy.get('[data-testid="recurrence-add"]').click()
      cy.chooseDaysOfTheWeek([5, 6])
      cy.get('[data-testid=save]').click()

      cy.get('button[type="submit"]').click()

      // Order page
      cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

      cy.get('[data-testid="order-total-including-tax"]')
        .find('[data-testid="value"]')
        .contains('€4.99')

      cy.get('[data-testid=delivery-itinerary]')
        .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
        .should('exist')
      cy.get('[data-testid=delivery-itinerary]')
        .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
        .should('exist')

      cy.get('a[href*="recurrence-rules"]').click()

      // Recurrence rule page
      cy.urlmatch(/\/admin\/stores\/[0-9]+\/recurrence-rules\/[0-9]+$/)

      // verify pickup time range:
      cy.betaTaskShouldHaveValue({
        taskFormIndex: 0,
        addressName: 'Warehouse',
        telephone: '01 12 12 12 12',
        contactName: 'John Doe',
        address: /23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/,
        timeAfter: '10:10',
        timeBefore: '11:20',
      })

      // verify dropoff time range:
      cy.betaTaskShouldHaveValue({
        taskFormIndex: 1,
        addressName: 'Office',
        telephone: '01 12 12 14 14',
        contactName: 'Jane smith',
        address: /72,? Rue Saint-Maur,? 75011,? Paris,? France/,
        timeAfter: '11:30',
        timeBefore: '12:40',
      })

      cy.get('[data-testid="tax-included"]').contains('4,99 €')
      cy.get('[data-testid="recurrence-container"]').contains(
        'chaque semaine le vendredi, samedi',
      )

      cy.go('back')

      cy.get('[data-testid="order-edit"]').click()

      // Delivery page
      cy.get('[data-testid="recurrence-container"]').should('not.exist')

      cy.betaTaskShouldHaveValue({
        taskFormIndex: 0,
        addressName: 'Warehouse',
        telephone: '01 12 12 12 12',
        contactName: 'John Doe',
        address: /23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/,
        date: '23 avril 2025',
        timeAfter: '10:10',
        timeBefore: '11:20',
      })

      cy.betaTaskShouldHaveValue({
        taskFormIndex: 1,
        addressName: 'Office',
        telephone: '01 12 12 14 14',
        contactName: 'Jane smith',
        address: /72,? Rue Saint-Maur,? 75011,? Paris,? France/,
        date: '23 avril 2025',
        timeAfter: '11:30',
        timeBefore: '12:40',
      })
    })
  })
})
