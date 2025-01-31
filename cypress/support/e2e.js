// ***********************************************************
// This example support/index.js is processed and
// loaded automatically before your test files.
//
// This is a great place to put global configuration and
// behavior that modifies Cypress.
//
// You can change the location of this file or turn off
// automatically serving support files with the
// 'supportFile' configuration option.
//
// You can read more here:
// https://on.cypress.io/configuration
// ***********************************************************

// Import commands.js using ES2015 syntax:
import './commands'

// Alternatively you can use CommonJS syntax:
// require('./commands')

Cypress.on('uncaught:exception', (err, runnable) => {

    if (err.message.includes('Request failed with status code 401')) {
      return false
    }

    // Started to fail sometimes while testing antd DatePicker
    // https://github.com/cypress-io/cypress/issues/29277
    if (err.message.includes("ResizeObserver loop")) {
      return false
    }

    // we still want to ensure there are no other unexpected
    // errors, so we let them fail the test
  })


// before(() => {
//   /* code that needs to run before all specs */
//   const prefix = Cypress.env('COMMAND_PREFIX')

//   let cmd =
//     'bin/console coopcycle:setup --env=test'
//   if (prefix) {
//     cmd = `${prefix} ${cmd}`
//   }

//   cy.exec(cmd)
// })