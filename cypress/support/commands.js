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

import moment from 'moment'

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
  // Reset previous mocks (if any)
  cy.resetMockDateTime()

  cy.symfonyConsole(`coopcycle:datetime:mock -d "${dateTime}"`)

  cy.clock(new Date(dateTime), ['Date']).then(clock => {
    // Set up a timer to tick the clock forward every 100ms
    const timerId = setInterval(() => {
      clock.tick(100, { log: false })
    }, 100)

    // Store the timer ID so it can be cleared later
    Cypress.env('clockTimer', timerId)
  })
})

Cypress.Commands.add('resetMockDateTime', () => {
  cy.symfonyConsole('coopcycle:datetime:mock --reset')

  // Clear the interval that's advancing the clock
  const timerId = Cypress.env('clockTimer')

  if (timerId) {
    clearInterval(timerId)
  }

  // cy.clock() will be reset automatically
})

Cypress.Commands.add('consumeMessages', (timeLimitInSeconds = 10) => {
  cy.symfonyConsole(`messenger:consume async --time-limit=${ timeLimitInSeconds }`);
})

Cypress.Commands.add('antdSelect', (selector, text) => {
  // open select
  cy.get(selector).click()

  cy.wait(1000)

  const toMoment = textValue => {
    if (/^\d{1,2}:\d{2}$/.test(textValue)) {
      const [hours, minutes] = textValue.split(':').map(Number)
      if (hours >= 0 && hours < 24 && minutes >= 0 && minutes < 60) {
        return moment().hours(hours).minutes(minutes)
      }
    }

    return null
  }

  cy.root({ log: false })
    .closest('body')
    .find('.ant-select-dropdown:visible')
    .not('.ant-select-dropdown-hidden')
    .within(() => {
      let attempts = 0
      const maxAttempts = 10

      function tryFindOption() {
        cy
          .get('.rc-virtual-list-holder-inner .ant-select-item-option', {
            log: false,
          })
          .then($options => {
            if (!$options || $options.length === 0) {
              cy.log(
                `No options found for selector "${selector}" with text "${text}"`,
              )
              throw new Error(
                `No options found for selector "${selector}" with text "${text}"`,
              )
            }

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

            // Fail early to debug test failures on CI
            const textMoment = toMoment(text)
            const firstOptionMoment = toMoment(
              $options.toArray()[0].textContent,
            )
            if (
              textMoment &&
              firstOptionMoment &&
              textMoment.isBefore(firstOptionMoment)
            ) {
              cy.log(
                `The text "${text}" is before the first option, skipping further attempts.`,
              )
              throw new Error(
                `The text "${text}" is before the first option, skipping further attempts.`,
              )
            }

            if (attempts >= maxAttempts) {
              cy.log(
                `Could not find option with text "${text}" after ${maxAttempts} scroll attempts`,
              )
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
            cy.wait(500)
            tryFindOption()
          })
      }

      tryFindOption()
    })
})

Cypress.Commands.add('reactSelect', (index) => {
  cy.get(`[id*="react-select-"][id*="-option-${index}"]`).click()
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

Cypress.Commands.add('shouldHaveValueIfVisible', (selector, value) => {
  cy.root().then($root => {
    if ($root.find(`${selector}:visible`).length > 0) {
      cy.get(selector).should('have.value', value)
    }
  })
})

/**
 * Validates a pricing rule condition
 */
// Example usage:
// cy.validatePricingRuleCondition({
//   type: 'packages',
//   operator: 'containsAtLeastOne',
//   packageName: 'XL',
// })
Cypress.Commands.add('validatePricingRuleCondition', condition => {
  cy.get('[data-testid="condition-type-select"]').should(
    'have.value',
    condition.type,
  )
  cy.get('[data-testid="condition-operator-select"]').should(
    'have.value',
    condition.operator,
  )

  if (condition.type === 'packages' && condition.packageName) {
    cy.get('[data-testid="condition-package-select"]').should(
      'contain',
      condition.packageName,
    )
  } else if (condition.type === 'time_slot' && condition.timeSlot) {
    cy.get('[data-testid="condition-time-slot-select"]').should(
      'contain',
      condition.timeSlot,
    )
  } else if (condition.value !== undefined) {
    cy.shouldHaveValueIfVisible(
      '[data-testid="condition-number-input"]',
      condition.value,
    )
    cy.shouldHaveValueIfVisible(
      '[data-testid="condition-task-type-select"]',
      condition.value,
    )
  }
})

/**
 * Validates multiple pricing rule conditions
 * @param {Array<Object>} conditions - Array of conditions to validate
 */
Cypress.Commands.add('validatePricingRuleConditions', conditions => {
  conditions.forEach((condition, index) => {
    cy.get(`[data-testid="condition-${index}"]`).within(() => {
      cy.validatePricingRuleCondition(condition)
    })
  })
})

Cypress.Commands.add('validatePricingRulePrice', price => {
  switch (price.type) {
    case 'fixed':
      cy.get('[data-testid="rule-fixed-price-input"]').should(
        'have.value',
        price.value,
      )
      break

    case 'percentage':
      cy.get('[data-testid="rule-price-type"]').should('contain', 'Pourcentage')
      cy.get('[data-testid="rule-percentage-input"]').should(
        'have.value',
        price.percentage,
      )
      break

    case 'range':
      cy.get('[data-testid="rule-price-type"]').should(
        'contain',
        'Prix TTC par tranches',
      )
      cy.get('[data-testid="rule-price-range-price"]').should(
        'have.value',
        price.range.price,
      )
      cy.get('[data-testid="rule-price-range-step"]').should(
        'have.value',
        price.range.step,
      )
      cy.get('[data-testid="rule-price-range-threshold"]').should(
        'have.value',
        price.range.threshold,
      )
      break

    case 'per_package':
      cy.get('[data-testid="rule-price-type"]').should(
        'contain',
        'Prix par colis',
      )
      cy.get('[data-testid="rule-per-package-name"]').should(
        'contain',
        price.perPackage.packageName,
      )
      cy.get('[data-testid="rule-per-package-unit-price"]').should(
        'have.value',
        price.perPackage.unitPrice,
      )
      cy.get('[data-testid="rule-per-package-offset"]').should(
        'have.value',
        price.perPackage.offset,
      )
      cy.get('[data-testid="rule-per-package-discount-price"]').should(
        'have.value',
        price.perPackage.discountPrice,
      )
      break

    default:
      throw new Error(`Unsupported price type: ${price.type}`)
  }
})

/**
 * Validates individual pricing rule
 */
// Example usage:
// cy.validatePricingRule({
//   index: 0,
//   conditions: [{ type: 'packages', operator: '==', packageName: 'SMALL' }],
//   price: { type: 'range', range: { price: 3, step: 2, threshold: 1 } }
// })
//
Cypress.Commands.add('validatePricingRule', rule => {
  cy.get(`[data-testid="pricing-rule-set-rule-${rule.index}"]`).should(
    'be.visible',
  )
  cy.get(`[data-testid="pricing-rule-set-rule-${rule.index}"]`).within(() => {
    if (rule.name) {
      cy.get('[data-testid="rule-name"]').should('have.value', rule.name)
    }

    if (rule.conditions && rule.conditions.length > 0) {
      cy.validatePricingRuleConditions(rule.conditions)
    }

    if (rule.price) {
      cy.validatePricingRulePrice(rule.price)
    }
  })
})

/**
 * Validates multiple pricing rules
 * @param {Array<Object>} rules - Array of rules to validate
 */
Cypress.Commands.add('validatePricingRules', rules => {
  rules.forEach(rule => {
    cy.validatePricingRule(rule)
  })
})

/**
 * Validates the pricing rule set form data
 */
// Example usage:
// cy.validatePricingRuleSet({
//   name: 'My Rule Set',
//   strategy: 'map',
//   deliveryRules: [
//     {
//       index: 0,
//       conditions: [{ type: 'distance', operator: '>', value: 0 }],
//       price: { type: 'fixed', value: 5 }
//     }
//   ]
// })
Cypress.Commands.add('validatePricingRuleSet', ruleSet => {
  // Validate form name
  cy.get('input[id*="name"]').should('have.value', ruleSet.name)

  // Validate strategy
  cy.get(`input[value="${ruleSet.strategy}"]`).should('be.checked')

  // Validate delivery rules
  if (ruleSet.deliveryRules && ruleSet.deliveryRules.length > 0) {
    cy.get('[data-testid="pricing-rule-set-target-delivery"]').within(() => {
      cy.get('[data-testid^="pricing-rule-set-rule-"]').should(
        'have.length',
        ruleSet.deliveryRules.length,
      )
    })
    cy.validatePricingRules(ruleSet.deliveryRules)
  }

  // Validate task rules
  if (ruleSet.taskRules && ruleSet.taskRules.length > 0) {
    cy.get('[data-testid="pricing-rule-set-target-task"]').within(() => {
      cy.get('[data-testid^="pricing-rule-set-rule-"]').should(
        'have.length',
        ruleSet.taskRules.length,
      )
    })
    cy.validatePricingRules(ruleSet.taskRules)
  }

  // Validate legacy rules
  if (ruleSet.legacyRules && ruleSet.legacyRules.length > 0) {
    cy.get('[data-testid="legacy-rules-section"]').within(() => {
      cy.get('[data-testid^="pricing-rule-set-rule-"]').should(
        'have.length',
        ruleSet.legacyRules.length,
      )
    })
    cy.validatePricingRules(ruleSet.legacyRules)
  }
})
