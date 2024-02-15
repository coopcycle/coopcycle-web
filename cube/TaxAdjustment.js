const fetch = require('node-fetch')

asyncModule(async () => {

  const { securityContext } = COMPILE_CONTEXT;

  let taxRates = []
  if (securityContext.hasOwnProperty('base_url')) {
    try {
      const response = await fetch(`${securityContext.base_url}/api/tax_rates`)
      if (response.status === 200) {
        const data = await response.json()
        taxRates = data['hydra:member']
      }
    } catch (e) {}
  }

  if (taxRates.length > 0) {

    const standardTaxRate     = taxRates.find(r => r.category === 'BASE_STANDARD')
    const intermediaryTaxRate = taxRates.find(r => r.category === 'BASE_INTERMEDIARY')
    const reducedTaxRate      = taxRates.find(r => r.category === 'BASE_REDUCED')

    const standardTaxRateCodes     = [ standardTaxRate.code, ...standardTaxRate.alternatives ]
    const intermediaryTaxRateCodes = [ intermediaryTaxRate.code, ...intermediaryTaxRate.alternatives ]
    const reducedTaxRateCodes      = [ reducedTaxRate.code, ...reducedTaxRate.alternatives ]

    cube(`TaxAdjustment`, {
      sql: `SELECT * FROM public.sylius_adjustment WHERE type = 'tax'`,
      extends: Adjustment,
      joins: {
        TaxRate: {
          relationship: `one_to_one`,
          sql: `${CUBE}.origin_code = ${TaxRate}.code`
        },
      },
      measures: {
        total_standard: {
          sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
          filters: [{
            // We can't use a JOIN with sylius_tax_category,
            // because it would cause an error for ajdustments that are not tax
            sql: `${CUBE}.origin_code IN ('${standardTaxRateCodes.join('\',\'')}')`
          }],
          type: `sum`,
        },
        total_intermediary: {
          sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
          filters: [{
            sql: `${CUBE}.origin_code IN ('${intermediaryTaxRateCodes.join('\',\'')}')`
          }],
          type: `sum`,
        },
        total_reduced: {
          sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
          filters: [{
            sql: `${CUBE}.origin_code IN ('${reducedTaxRateCodes.join('\',\'')}')`
          }],
          type: `sum`,
        },
      },
    });

  } else {

    cube(`TaxAdjustment`, {
      sql: `SELECT * FROM public.sylius_adjustment WHERE type = 'tax'`,
      extends: Adjustment,
      joins: {
        TaxRate: {
          relationship: `one_to_one`,
          sql: `${CUBE}.origin_code = ${TaxRate}.code`
        },
      },
      measures: {
        total_standard: {
          sql: `0`,
          type: `number`,
        },
        total_intermediary: {
          sql: `0`,
          type: `number`,
        },
        total_reduced: {
          sql: `0`,
          type: `number`,
        },
      },
    });

  }
})
