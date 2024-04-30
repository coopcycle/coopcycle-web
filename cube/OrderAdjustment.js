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

  const sql = `
    SELECT
      COALESCE(a.order_id, i.order_id) AS order_id,
      a.type,
      a.amount,
      a.origin_code
    FROM sylius_adjustment a
    LEFT JOIN sylius_order_item i ON a.order_item_id = i.id
  `

  const dimensions = {
    order_id: {
      sql: () => `order_id`,
      type: `number`,
      primaryKey: true
    },
    type: {
      sql: () => `type`,
      type: `string`,
    },
    amount: {
      sql: () => `amount`,
      type: `number`,
    },
    origin_code: {
      sql: () => `origin_code`,
      type: `string`,
    },
  }

  if (taxRates.length > 0) {

    const standardTaxRate     = taxRates.find(r => r.category === 'BASE_STANDARD')
    const intermediaryTaxRate = taxRates.find(r => r.category === 'BASE_INTERMEDIARY')
    const reducedTaxRate      = taxRates.find(r => r.category === 'BASE_REDUCED')

    const standardTaxRateCodes     = [ standardTaxRate.code, ...standardTaxRate.alternatives ]
    let intermediaryTaxRateCodes   = []
    let reducedTaxRateCodes        = []

    if (intermediaryTaxRate) {
      intermediaryTaxRateCodes = [ intermediaryTaxRate.code, ...intermediaryTaxRate.alternatives ]
    }

    if (reducedTaxRate) {
      reducedTaxRateCodes = [ reducedTaxRate.code, ...reducedTaxRate.alternatives ]
    }

    intermediaryTaxRateCodes = intermediaryTaxRateCodes.length > 0 ? intermediaryTaxRateCodes : ['N/A']
    reducedTaxRateCodes = reducedTaxRateCodes.length > 0 ? reducedTaxRateCodes : ['N/A']

    cube(`OrderAdjustment`, {
      sql,
      joins: {
        Order: {
          relationship: `many_to_one`,
          sql: `${CUBE}.order_id = ${Order}.id`
        },
      },
      dimensions,
      dataSource: `default`,
      measures: {
        stripe_fee: {
          type: `sum`,
          sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
          filters: [{ sql: `${CUBE}.type = 'stripe_fee'` }],
          format: `currency`,
        },
        platform_fee: {
          type: `sum`,
          sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
          filters: [{ sql: `${CUBE}.type = 'fee'` }],
          format: `currency`,
        },
        packaging_fee: {
          type: `sum`,
          sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
          filters: [{ sql: `${CUBE}.type = 'reusable_packaging'` }],
          format: `currency`,
        },
        delivery_fee: {
          type: `sum`,
          sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
          filters: [{ sql: `${CUBE}.type = 'delivery'` }],
          format: `currency`,
        },
        promotions: {
          type: `sum`,
          sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
          filters: [{ sql: `${CUBE}.type IN ('delivery_promotion', 'order_promotion')` }],
          format: `currency`,
        },
        tip: {
          type: `sum`,
          sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
          filters: [{ sql: `${CUBE}.type = 'tip'` }],
          format: `currency`,
        },
        tax_total: {
          type: `sum`,
          sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
          filters: [{ sql: `${CUBE}.type = 'tax'` }],
          format: `currency`,
        },
        tax_total_standard: {
          type: `sum`,
          sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
          filters: [{ sql: `${CUBE}.type = 'tax' AND origin_code IN ('${standardTaxRateCodes.join('\',\'')}')` }],
          format: `currency`,
        },
        tax_total_intermediary: {
          type: `sum`,
          sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
          filters: [{ sql: `${CUBE}.type = 'tax' AND origin_code IN ('${intermediaryTaxRateCodes.join('\',\'')}')` }],
          format: `currency`,
        },
        tax_total_reduced: {
          type: `sum`,
          sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
          filters: [{ sql: `${CUBE}.type = 'tax' AND origin_code IN ('${reducedTaxRateCodes.join('\',\'')}')` }],
          format: `currency`,
        },
        total_excl_tax: {
          type: `number`,
          sql: `${CUBE.Order.total} - ${CUBE.tax_total}`,
          format: `currency`,
        },
        total_excl_tax_standard: {
          type: `number`,
          sql: `${CUBE.Order.total} - ${CUBE.tax_total_standard}`,
          format: `currency`,
        },
        total_excl_tax_intermediary: {
          type: `number`,
          sql: `${CUBE.Order.total} - ${CUBE.tax_total_intermediary}`,
          format: `currency`,
        },
        total_excl_tax_reduced: {
          type: `number`,
          sql: `${CUBE.Order.total} - ${CUBE.tax_total_reduced}`,
          format: `currency`,
        },
        revenue: {
          // Use COALESCE when there is no corresponding row with JOIN
          sql: `${CUBE.Order.total} - COALESCE(${CUBE.platform_fee}, 0) - COALESCE(${CUBE.stripe_fee}, 0)`,
          type: `number`,
          format: `currency`
        },
      },
    })

  } else {
    cube(`OrderAdjustment`, {
      sql,
      joins: {
        Order: {
          relationship: `many_to_one`,
          sql: `${CUBE}.order_id = ${Order}.id`
        },
      },
      dimensions,
      dataSource: `default`,
      measures: {
        stripe_fee: {
          type: `sum`,
          sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
          filters: [{ sql: `${CUBE}.type = 'stripe_fee'` }],
          format: `currency`,
        },
        platform_fee: {
          type: `sum`,
          sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
          filters: [{ sql: `${CUBE}.type = 'fee'` }],
          format: `currency`,
        },
        packaging_fee: {
          type: `sum`,
          sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
          filters: [{ sql: `${CUBE}.type = 'reusable_packaging'` }],
          format: `currency`,
        },
        delivery_fee: {
          type: `sum`,
          sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
          filters: [{ sql: `${CUBE}.type = 'delivery'` }],
          format: `currency`,
        },
        promotions: {
          type: `sum`,
          sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
          filters: [{ sql: `${CUBE}.type IN ('delivery_promotion', 'order_promotion')` }],
          format: `currency`,
        },
        tip: {
          type: `sum`,
          sql: `ROUND(${CUBE}.amount / 100::numeric, 2)`,
          filters: [{ sql: `${CUBE}.type = 'tip'` }],
          format: `currency`,
        },
        tax_total: {
          type: `sum`,
          sql: `0`,
          format: `currency`,
        },
        tax_total_standard: {
          type: `number`,
          sql: `0`,
          format: `currency`,
        },
        tax_total_intermediary: {
          type: `number`,
          sql: `0`,
          format: `currency`,
        },
        tax_total_reduced: {
          type: `number`,
          sql: `0`,
          format: `currency`,
        },
        total_excl_tax: {
          type: `number`,
          sql: `${CUBE.Order.total} - ${CUBE.tax_total}`,
          format: `currency`,
        },
        total_excl_tax_standard: {
          type: `number`,
          sql: `${CUBE.Order.total} - ${CUBE.tax_total_standard}`,
          format: `currency`,
        },
        total_excl_tax_intermediary: {
          type: `number`,
          sql: `${CUBE.Order.total} - ${CUBE.tax_total_intermediary}`,
          format: `currency`,
        },
        total_excl_tax_reduced: {
          type: `number`,
          sql: `${CUBE.Order.total} - ${CUBE.tax_total_reduced}`,
          format: `currency`,
        },
        revenue: {
          // Use COALESCE when there is no corresponding row with JOIN
          sql: `${CUBE.Order.total} - COALESCE(${CUBE.platform_fee}, 0) - COALESCE(${CUBE.stripe_fee}, 0)`,
          type: `number`,
          format: `currency`
        },
      },
    })
  }

})
