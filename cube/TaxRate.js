cube(`TaxRate`, {
  sql_table: `public.sylius_tax_rate`,
  dimensions: {
    amount: {
      sql: `amount`,
      type: `number`
    }
  },
  dataSource: `default`
})
