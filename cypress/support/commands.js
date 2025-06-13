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
  cy.log(`exec: ${command}`)

  const prefix = Cypress.env('COMMAND_PREFIX')
  cy.exec(prefix ? `${prefix} ${command}` : command, {timeout: 60000, log: false})
})

Cypress.Commands.add('symfonyConsole', command => {
  cy.terminal(`bin/console ${command} --env="test"`)
})

Cypress.Commands.add('loadFixtures', (fixtures, setup=false) => {
  const fixturesString = (Array.isArray(fixtures) ? fixtures : [fixtures]).map(f => `-f fixtures/${f}`).join(' ')
  cy.symfonyConsole(`coopcycle:fixtures:load${setup ? ' -s cypress/fixtures/setup_default.yml' : ''} ${fixturesString}`)
})

Cypress.Commands.add('loadFixturesWithSetup', fixtures => {
  cy.loadFixtures(fixtures, true)
})

Cypress.Commands.add('urlmatch', (pattern, type='match', from='pathname') => {
  cy.location(from, { timeout: 10000 }).should(type, pattern)
})

Cypress.Commands.add('getIfExists', (selector, callbackWhenNotFound=null) => {
  cy.document().then(($document) => {
    if ($document.querySelectorAll(selector).length) {
      return cy.get(selector, { timeout: 5000 }).should('exist')
    }

    return cy.log(`The element '${selector}' was not found in DOM!`).then(() => {
        return callbackWhenNotFound ? callbackWhenNotFound(selector) : null
      })
  })
})

Cypress.Commands.add('setMockDateTime', dateTime => {
  cy.symfonyConsole(`coopcycle:datetime:mock -d "${dateTime}"`)

  cy.clock(new Date(dateTime), ['Date']).then((clock) => {
    // Set up a timer to tick the clock forward every 100ms
    const timer = setInterval(() => {
      clock.tick(100, { log: false });
    }, 100);

    // Store the timer ID so it can be cleared later
    Cypress.env('clockTimer', timer);
  })
})

Cypress.Commands.add('resetMockDateTime', () => {
  cy.symfonyConsole('coopcycle:datetime:mock --reset')

  // Clear the interval that's advancing the clock
  const timer = Cypress.env('clockTimer');
  if (timer) {
    clearInterval(timer);
  }

  // cy.clock() will be reset automatically
})

Cypress.Commands.add('consumeMessages', (timeLimitInSeconds = 10) => {
  cy.symfonyConsole(`messenger:consume async --time-limit=${ timeLimitInSeconds }`);
})

Cypress.Commands.add('antdSelect', (selector, text) => {
  // open select
  cy.get(selector).click()

  cy.wait(300)

  cy.root({ log: false })
    .closest('body')
    .find('.ant-select-dropdown:visible')
    .not('.ant-select-dropdown-hidden')
    .within(() => {
      let attempts = 0
      const maxAttempts = 10

      function tryFindOption() {
        return cy
          .get('.rc-virtual-list-holder-inner .ant-select-item-option', {
            log: false,
          })
          .then($options => {
            cy.log(
              `Searching for option with text "${text}"; elements: "${$options
                .toArray()
                .map(el => el.textContent)
                .join(', ')}"`,
            )
            const option = $options.filter((index, el) =>
              el.textContent.includes(text),
            )

            if (option.length) {
              cy.wrap(option).click()
              return
            }

            if (attempts >= maxAttempts) {
              throw new Error(
                `Could not find option with text "${text}" after ${maxAttempts} scroll attempts`,
              )
            }

            attempts++

            cy.get('.rc-virtual-list-holder').trigger('wheel', {
              deltaX: 0,
              deltaY: 32 * 6, // 1 row = ~32px
              deltaMode: 0,
            })
            cy.wait(100)
            tryFindOption()
          })
      }

      tryFindOption()
    })
})

Cypress.Commands.add('clickRestaurant', (name, pathnameRegexp) => {
  cy.contains(name).click()
  cy.urlmatch(pathnameRegexp)
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
  cy.visit('/login')
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

  cy.get(`${ selector } input[type="search"][data-is-address-picker="true"]`, { timeout: 5000 })
    .should('be.visible')

  cy.get(`${ selector } input[type="search"][data-is-address-picker="true"]`)
    .eq(index)
    .clear()

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
    businessName, telephone, contactName) => {

    cy.searchAddress(
      `[data-testid=form-task-${taskFormIndex}]`,
      addressSearch,
      addressMatch,
    )

    cy.get(`input[name="tasks[${taskFormIndex}].address.name"]`).clear()
    cy.get(`input[name="tasks[${taskFormIndex}].address.name"]`).type(businessName)

    cy.get(`input[name="tasks[${taskFormIndex}].address.formattedTelephone"]`).clear()
    cy.get(`input[name="tasks[${taskFormIndex}].address.formattedTelephone"]`).type(telephone)

    cy.get(`input[name="tasks[${taskFormIndex}].address.contactName"]`).clear()
    cy.get(`input[name="tasks[${taskFormIndex}].address.contactName"]`).type(contactName)

  })

Cypress.Commands.add('betaEnterWeightAtPosition',
  (taskFormIndex, weight) => {

    cy.get(`[name="tasks[${taskFormIndex}].weight"]`).clear()
    cy.get(`[name="tasks[${taskFormIndex}].weight"]`).type(weight)

  })

Cypress.Commands.add('betaEnterCommentAtPosition',
  (taskFormIndex, comments) => {

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

Cypress.Commands.add('betaChooseSavedAddressAtPosition',
  (taskFormIndex, addressIndex) => {

    cy.get(`[data-testid="form-task-${taskFormIndex}"]`).within(() => {
      cy.get('[data-testid="address-select"]').click()
      cy.wait(300)
      cy.root()
        .closest('body')
        .find('.ant-select-dropdown:visible')
        .not('.ant-select-dropdown-hidden')
        .within(() => {
          cy.get(`.rc-virtual-list-holder-inner > :nth-child(${ addressIndex })`).click()
        })
    })
  })

Cypress.Commands.add(
  'betaTaskShouldHaveValue',
  ({
    taskFormIndex,
    addressName,
    telephone,
    contactName,
    address,
    date,
    hourRange,
    timeAfter,
    timeBefore,
    packages,
    weight,
    comments,
    tags,
  }) => {
    cy.get(`[data-testid="form-task-${taskFormIndex}"]`).within(() => {
      cy.get('.task__header').contains(address).should('exist')

      cy.get(`[data-testid=address-select]`).within(() => {
        cy.contains(addressName).should('exist')
      })

      cy.get(`.address-infos`).within(() => {
        cy.get(`[name="tasks[${taskFormIndex}].address.name"]`).should(
          'have.value',
          addressName,
        )
        cy.get(
          `[name="tasks[${taskFormIndex}].address.formattedTelephone"]`,
        ).should('have.value', telephone)
        cy.get(`[name="tasks[${taskFormIndex}].address.contactName"]`).should(
          'have.value',
          contactName,
        )
      })

      cy.get(`.address__autosuggest`).within(() => {
        cy.get('input').invoke('val').should('match', address)
      })

      if (date !== undefined) {
        cy.get(`[data-testid=date-picker]`).should('have.value', date)
      }

      if (hourRange !== undefined) {
        cy.get(`[data-testid=hour-picker]`).within(() => {
          cy.contains(hourRange).should('exist')
        })
      }

      if (timeAfter && timeBefore) {
        cy.get(`[data-testid=select-after]`).within(() => {
          cy.contains(timeAfter).should('exist')
        })
        cy.get(`[data-testid=select-before]`).within(() => {
          cy.contains(timeBefore).should('exist')
        })
      }

      if (packages !== undefined) {
        packages.forEach(pkg => {
          cy.get(`[data-testid="${pkg.nodeId}"]`).within(() => {
            cy.get('input').should('have.value', pkg.quantity)
          })
        })
      }

      if (weight !== undefined) {
        cy.get(`[name="tasks[${taskFormIndex}].weight"]`).should(
          'have.value',
          weight,
        )
      }

      if (comments !== undefined) {
        cy.get(`[name="tasks[${taskFormIndex}].comments"]`)
          .contains(comments)
          .should('exist')
      }

      if (tags !== undefined) {
        cy.get(`[data-testid=tags-select]`).within(() => {
          tags.forEach(tag => {
            cy.contains(tag).should('exist')
          })
        })
      }
    })
  },
)

Cypress.Commands.add(
  'betaTaskCollapsedShouldHaveValue',
  ({ taskFormIndex, address }) => {
    cy.get(`[data-testid="form-task-${taskFormIndex}"]`).within(() => {
      cy.get('.task__header').contains(address).should('exist')

      cy.get(`[data-testid=address-select]`).should('not.be.visible')
    })
  },
)

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
  cy.get('[data-testid="recurrence__modal__content"]')
    .within(() => {
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
})
