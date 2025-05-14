context('Delivery (role: admin)', () => {
  beforeEach(() => {
    cy.loadFixturesWithSetup([
      "user_admin.yml",
      "../../fixtures/ORM/tags.yml",
      "../../features/fixtures/ORM/store_default.yml",
      ])

    cy.setMockDateTime('2025-04-23 8:30:00')

    cy.login('admin', '12345678')
  })

  afterEach(() => {
    cy.resetMockDateTime()
  })

  it('[beta form] create delivery order', function () {
    cy.visit('/admin/stores')

    cy.get('[data-testid=store_Acme__list_item]')
      .find('.dropdown-toggle')
      .click()

    cy.get('[data-testid=store_Acme__list_item]')
      .contains('Créer une livraison')
      .click()

    cy.get('body > div.content > div > div > div > a')
      .contains('click here')
      .click()

    // Pickup

    cy.betaEnterAddressAtPosition(
      0,
      '23 Avenue Claude Vellefaux, 75010 Paris, France',
      /^23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/i,
      'Warehouse',
      '+33112121212',
      'John Doe',
      'Pickup comments'
    )

    cy.get(`[data-testid="form-task-${0}"]`).within(() => {
      cy.get(`[data-testid=tags-select]`).click()
    })
    cy.get('#react-select-3-option-0').click();


    // Dropoff

    cy.betaEnterAddressAtPosition(
      1,
      '72 Rue Saint-Maur, 75011 Paris, France',
      /^72,? Rue Saint-Maur,? 75011,? Paris,? France/i,
      'Office',
      '+33112121414',
      'Jane smith',
      'Dropoff comments'
    )

    cy.get(`[data-testid="form-task-${1}"]`).within(() => {
      cy.get('[data-testid="/api/packages/1"] > .packages-item__quantity > :nth-child(3)').click();
    })
    cy.get(`[name="tasks[${1}].weight"]`).type(2.5)

    cy.get(`[data-testid="form-task-${1}"]`).within(() => {
      cy.get(`[data-testid=tags-select]`).click()
    })
    cy.get('#react-select-5-option-2').click();

    cy.get('[data-testid="tax-included"]').contains('4,99 €')

    cy.get('button[type="submit"]').click()

    // list of deliveries page
    // TODO : check for proper redirect when implemented
    // cy.urlmatch(/\/admin\/stores\/[0-9]+\/deliveries$/)

    cy.urlmatch(/\/admin\/deliveries$/)

    cy.get('[data-testid=delivery__list_item]', { timeout: 10000 })
      .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery__list_item]')
      .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
      .should('exist')
    cy.get('[data-testid=delivery__list_item]')
      .contains(/€4.99/)
      .should('exist')

    cy.get('[data-testid="delivery__list_item"]')
      .find('[data-testid="delivery_id"]')
      .click()

    // Edit Delivery page

    cy.get('body > div.content > div > div > div > a')
      .contains('click here')
      .click()

    //verify that all the fields are saved correctly

    cy.get(`[data-testid="form-task-${0}"]`).within(() => {
      cy.get('.task__header')
        .contains(/23,? Avenue Claude Vellefaux,? 75010,? Paris,? France/)
        .should('exist')

      cy.get(`[data-testid=address-select]`).within(() => {
        cy.contains('Warehouse').should('exist')
      })

      cy.get(`.address-infos`).within(() => {
        cy.get('[name="tasks[0].address.name"]').should('have.value', 'Warehouse')
        cy.get('[name="tasks[0].address.formattedTelephone"]').should('have.value', '01 12 12 12 12')
        cy.get('[name="tasks[0].address.contactName"]').should('have.value', 'John Doe')
      });

      cy.get(`.address__autosuggest`).within(() => {
        cy.get('input')
          .should('have.value', '23, Avenue Claude Vellefaux, 75010, Paris, France')
      })

      cy.get('[data-testid=date-picker]')
        .should('have.value', '23 avril 2025')
      cy.get(`[data-testid=select-after]`).within(() => {
        cy.contains('00:00').should('exist')
      })
      cy.get(`[data-testid=select-before]`).within(() => {
        cy.contains('11:59').should('exist')
      })

      cy.get(`[name="tasks[${0}].comments"]`)
        .contains('Pickup comments')
        .should('exist')

      cy.get(`[data-testid=tags-select]`).within(() => {
        cy.contains('Important').should('exist')
      });

    })

    cy.get(`[data-testid="form-task-${1}"]`).within(() => {
      cy.get('.task__header')
        .contains(/72,? Rue Saint-Maur,? 75011,? Paris,? France/)
        .should('exist')

      cy.get(`[data-testid=address-select]`).within(() => {
        cy.contains('Office').should('exist')
      })

      cy.get(`.address-infos`).within(() => {
        cy.get('[name="tasks[1].address.name"]').should('have.value', 'Office')
        cy.get('[name="tasks[1].address.formattedTelephone"]').should('have.value', '01 12 12 14 14')
        cy.get('[name="tasks[1].address.contactName"]').should('have.value', 'Jane smith')
      });

      cy.get(`.address__autosuggest`).within(() => {
        cy.get('input')
          .should('have.value', '72, Rue Saint-Maur, 75011, Paris, France')
      })

      cy.get('[data-testid=date-picker]')
        .should('have.value', '23 avril 2025')
      cy.get(`[data-testid=select-after]`).within(() => {
        cy.contains('00:00').should('exist')
      })
      cy.get(`[data-testid=select-before]`).within(() => {
        cy.contains('11:59').should('exist')
      })

      cy.get(`[data-testid="/api/packages/1"]`).within(() => {
        cy.get('input').should('have.value', '1')
      })

      cy.get('[name="tasks[1].weight"]').should('have.value', '2.5')

      cy.get(`[name="tasks[${1}].comments"]`)
        .contains('Dropoff comments')
        .should('exist')

      cy.get(`[data-testid=tags-select]`).within(() => {
        cy.contains('Perishable').should('exist')
      });

    })

    cy.get('[data-testid="tax-included-previous"]').contains('4,99 €')


    cy.get('[data-testid="breadcrumb"]')
      .find('[data-testid="order_id"]')
      .should('exist')

    cy.get('[data-testid="breadcrumb"]')
      .find('[data-testid="order_id"]')
      .click()

    // Order page
    cy.urlmatch(/\/admin\/orders\/[0-9]+$/)

    cy.get('[data-testid="order_item"]')
      .find('[data-testid="total"]')
      .contains('€4.99')
  })
})
