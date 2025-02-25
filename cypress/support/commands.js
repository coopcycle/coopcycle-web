// ***********************************************
// This example commands.js shows you how to
// create various custom commands and overwrite
// existing commands.
//
// For more comprehensive examples of custom
// commands please read more here:
// https://on.cypress.io/custom-commands
// ***********************************************
//
//
// -- This is a parent command --
// Cypress.Commands.add("login", (email, password) => { ... })
//
//
// -- This is a child command --
// Cypress.Commands.add("drag", { prevSubject: 'element'}, (subject, options) => { ... })
//
//
// -- This is a dual command --
// Cypress.Commands.add("dismiss", { prevSubject: 'optional'}, (subject, options) => { ... })
//
//
// -- This is will overwrite an existing command --
// Cypress.Commands.overwrite("visit", (originalFn, url, options) => { ... })

Cypress.Commands.add('terminal', command => {
  const prefix = Cypress.env('COMMAND_PREFIX')
  let cmd = `${command}`
  if (prefix) {
    cmd = `${prefix} ${cmd}`
  }
  cy.exec(cmd)
})

Cypress.Commands.add('symfonyConsole', command => {
  const prefix = Cypress.env('COMMAND_PREFIX')
  let cmd = `bin/console ${command} --env="test"`
  if (prefix) {
    cmd = `${prefix} ${cmd}`
  }
  cy.exec(cmd)
})

Cypress.Commands.add('clickRestaurant', (name, pathnameRegexp) => {
  cy.contains(name).click()
  cy.location('pathname').should('match', pathnameRegexp)
})

Cypress.Commands.add('addProduct',
  (name, detailsModalSelector, quantity = undefined,
    optionItemsSelectors = []) => {
    cy.contains(name).click()

    cy.get(detailsModalSelector)
      .should('be.visible')

    if (quantity) {
      cy.get(
        `${ detailsModalSelector } .quantity-input-group input[type="number"]`)
        .type('{backspace}' + quantity)
    }

    // Make sure to use a precise selector, because 2 products have same options

    optionItemsSelectors.forEach((itemSelector) => {
      cy.get(`${ detailsModalSelector } input[value="${ itemSelector }"]`)
        .check()
    })

    optionItemsSelectors.forEach((itemSelector) => {
      cy.get(`${ detailsModalSelector } input[value="${ itemSelector }"]`)
        .should('be.checked')
    })

    cy.get(`${ detailsModalSelector } button[type="submit"]`)
      .should('not.be.disabled')
      .click()
  })

Cypress.Commands.add('login', (username, password) => {
  cy.get('[name="_username"]').type(username)
  cy.get('[name="_password"]').type(password)
  cy.get('[name="_submit"]').click()
})

Cypress.Commands.add('searchAddressUsingAddressModal', (selector, search, match) => {
  cy.searchAddress(selector, search, match, 1) // take the 2nd input on the restaurant page. to be changed when fix for https://github.com/coopcycle/coopcycle-web/issues/4149
})

Cypress.Commands.add('searchAddress', (selector, search, match, index = 0) => {
  cy.get(selector)
    .should('be.visible')

  cy.wait(500)

  cy.get(`${ selector } input[type="search"][data-is-address-picker="true"]`)
    .should('be.visible')

  cy.get(`${ selector } input[type="search"][data-is-address-picker="true"]`)
    .eq(index)
    .type(search, { timeout: 5000, delay: 50 })

  cy.get(selector)
    .find('ul[role="listbox"] li', { timeout: 10000 })
    .contains(match)
    .click()
})

Cypress.Commands.add('newPickupAddress',
  (addressSelector, addressSearch, addressMatch,
    businessName, telephone, contactName) => {
    cy.searchAddress(
      addressSelector,
      addressSearch,
      addressMatch,
    )

    cy.get('#delivery_tasks_0_address_name__display').clear()
    cy.get('#delivery_tasks_0_address_name__display').type(businessName)

    cy.get('#delivery_tasks_0_address_telephone__display').clear()
    cy.get('#delivery_tasks_0_address_telephone__display').type(telephone)

    cy.get('#delivery_tasks_0_address_contactName__display').clear()
    cy.get('#delivery_tasks_0_address_contactName__display').type(contactName)
  })

  Cypress.Commands.add('betaEnterAddressAtPosition',
    (taskFormIndex, addressSearch, addressMatch,
      businessName, telephone, contactName, comments) => {

      cy.searchAddress(
        `[data-testid-form=task-${taskFormIndex}]`,
        addressSearch,
        addressMatch,
      )
  
      cy.get(`input[name="tasks[${taskFormIndex}].address.name"]`).clear()
      cy.get(`input[name="tasks[${taskFormIndex}].address.name"]`).type(businessName)

      cy.get(`input[name="tasks[${taskFormIndex}].address.formattedTelephone"]`).clear()
      cy.get(`input[name="tasks[${taskFormIndex}].address.formattedTelephone"]`).type(telephone)

      cy.get(`input[name="tasks[${taskFormIndex}].address.contactName"]`).clear()
      cy.get(`input[name="tasks[${taskFormIndex}].address.contactName"]`).type(contactName)

      cy.get(`[name="tasks[${taskFormIndex}].comments"]`).clear()
      cy.get(`[name="tasks[${taskFormIndex}].comments"]`).type(comments)
  
    })

Cypress.Commands.add('chooseSavedPickupAddress',
  (index) => {
    cy.get('#rc_select_0').click()
    cy.get(`.rc-virtual-list-holder-inner > :nth-child(${ index })`).click()
  })

Cypress.Commands.add('newDropoff1Address',
  (addressSelector, addressSearch, addressMatch,
    businessName, telephone, contactName) => {
    cy.searchAddress(
      addressSelector,
      addressSearch,
      addressMatch,
    )

    cy.get('#delivery_tasks_1_address_name__display').clear()
    cy.get('#delivery_tasks_1_address_name__display').type(businessName)

    cy.get('#delivery_tasks_1_address_telephone__display').clear()
    cy.get('#delivery_tasks_1_address_telephone__display').type(telephone)

    cy.get('#delivery_tasks_1_address_contactName__display').clear()
    cy.get('#delivery_tasks_1_address_contactName__display').type(contactName)
  })

Cypress.Commands.add('chooseSavedDropoff1Address',
  (index) => {
    cy.get('#rc_select_1').click()
    cy.get(`.rc-virtual-list-holder-inner > :nth-child(${ index }):visible`).click()
  })

Cypress.Commands.add('enterCreditCard', () => {
  const date = new Date(),
    expDate = ('0' + (date.getMonth() + 1)).slice(-2) +
      date.getFullYear().toString().substring(2)

  // @see https://github.com/cypress-io/cypress/issues/136
  cy.get('.StripeElement iframe')
    .then(function ($iframe) {

      const $body = $iframe.contents().find('body')

      cy
        .wrap($body)
        .find('input[name="cardnumber"]', { timeout: 5000, delay: 30 })
        .type('4242424242424242')

      cy
        .wrap($body)
        .find('input[name="exp-date"]', { timeout: 5000, delay: 30 })
        .type(expDate)

      cy
        .wrap($body)
        .find('input[name="cvc"]', { timeout: 5000, delay: 30 })
        .type('123')
    })
})

/**
 * Clears cookies before executing a callback and then restores the cookies.
 * see https://github.com/cypress-io/cypress/issues/959#issuecomment-1373985148
 * @param {Function} callback
 * @param {{ domain, log, timeout }} options https://docs.cypress.io/api/commands/getcookies#Arguments
 */
Cypress.Commands.add('ignoreCookiesOnce', (callback, options) => {
  return cy.getCookies(options).then(cookies => {
    // Clear cookies
    cy.clearCookies(options)

    // Execute callback
    callback()

    // Clear cookies set by the callback
    cy.clearCookies(options)

    // Restore cookies
    cookies.forEach(({ name, value, ...rest }) => {
      cy.setCookie(name, value, rest)
    })
  })
})

Cypress.Commands.add('closeRestaurantForToday',
  (ownerUsername, ownerPassword) => {
    cy.ignoreCookiesOnce(() => {
      //get API token
      cy.request({
        method: 'POST',
        url: '/api/login_check',
        headers: {
          ContentType: 'application/x-www-form-urlencoded',
        },
        body: {
          _username: ownerUsername,
          _password: ownerPassword,
        },
      }).then((loginResponse) => {
        const token = loginResponse.body.token

        cy.request({
          method: 'GET',
          url: '/api/me/restaurants',
          headers: {
            Authorization: `Bearer ${ token }`,
          },
        }).then((myRestaurantsResponse) => {
          myRestaurantsResponse.body['hydra:member'].forEach((restaurant) => {
            cy.request({
              method: 'PUT',
              url: '/api/restaurants/' + restaurant.id + '/close',
              headers: {
                Authorization: `Bearer ${ token }`,
                ContentType: 'application/json',
              },
              body: {},
            }).then(() => {
              cy.log(
                `Restaurant ${ restaurant.id }; ${ restaurant.name } is closed`)
            })
          })
        })
      })
    })
  })

Cypress.Commands.add('chooseDaysOfTheWeek', (daysOfTheWeek) => {
  for (let i = 1; i < 7; i++) {
    if (daysOfTheWeek.includes(i)) {
      cy.get(`:nth-child(${ i }) > .ant-checkbox > .ant-checkbox-input`)
        .check()
    } else {
      cy.get(`:nth-child(${ i }) > .ant-checkbox > .ant-checkbox-input`)
        .uncheck()
    }
  }
})
