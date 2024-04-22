cube(`Order`, {
  sql: `SELECT * FROM public.sylius_order`,

  joins: {
    OrderVendor: {
      relationship: `one_to_many`,
      sql: `${CUBE}.id = ${OrderVendor}.order_id`
    },
    Address: {
      relationship: `one_to_one`,
      sql: `${CUBE}.shipping_address_id = ${Address}.id`
    },
    OrderFulfilledEvent: {
      relationship: `one_to_one`,
      sql: `${CUBE}.id = ${OrderFulfilledEvent}.aggregate_id`
    },
    OrderAdjustment: {
      relationship: `one_to_many`,
      sql: `${CUBE}.id = ${OrderAdjustment}.order_id`,
    },
    MessengerWithOrder: {
      relationship: `one_to_one`,
      sql: `${CUBE}.id = ${MessengerWithOrder}.id`,
    },
    Delivery: {
      relationship: `one_to_one`,
      sql: `${CUBE}.id = ${Delivery}.order_id`,
    },
    OrderItem: {
      relationship: `one_to_many`,
      sql: `${CUBE}.id = ${OrderItem}.order_id`,
    },
    Payment: {
      relationship: `one_to_many`,
      sql: `${CUBE}.id = ${Payment}.order_id`,
    },
  },

  measures: {
    count: {
      type: `count`,
      drillMembers: [id]
    },

    adjustmentsTotal: {
      sql: `adjustments_total`,
      type: `sum`
    },

    averageTotal: {
      sql: `ROUND(${CUBE}.total / 100::numeric, 2)`,
      type: `avg`
    },

    deliveryFee: {
      sql: `${CUBE.OrderAdjustment.amount}`,
      type: `sum`,
      format: `currency`,
      filters: [{ sql: `${CUBE.OrderAdjustment.type} = 'delivery'` }],
    },

    vendorCount: {
      sql: `${CUBE.OrderVendor.count}`,
      type: `number`,
    },

    tip: {
      sql: `${CUBE.OrderAdjustment.amount}`,
      type: `sum`,
      format: `currency`,
      filters: [{ sql: `${CUBE.OrderAdjustment.type} = 'tip'` }],
    },

    packagingFee: {
      sql: `${CUBE.OrderAdjustment.amount}`,
      type: `sum`,
      format: `currency`,
      filters: [{ sql: `${CUBE.OrderAdjustment.type} = 'reusable_packaging'` }],
    },

    promotions: {
      sql: `${CUBE.OrderAdjustment.amount}`,
      type: `sum`,
      format: `currency`,
      filters: [{ sql: `${CUBE.OrderAdjustment.type} IN ('delivery_promotion', 'order_promotion')` }],
    },

    stripeFee: {
      sql: `${CUBE.OrderAdjustment.amount}`,
      type: `sum`,
      format: `currency`,
      filters: [{ sql: `${CUBE.OrderAdjustment.type} = 'stripe_fee'` }],
    },

    platformFee: {
      sql: `${CUBE.OrderAdjustment.amount}`,
      type: `sum`,
      format: `currency`,
      filters: [{ sql: `${CUBE.OrderAdjustment.type} = 'fee'` }],
    },

    itemsTaxTotal: {
      sql: `${CUBE.OrderItem.taxTotal}`,
      type: `number`,
      format: `currency`
    },

    // revenue: {
    //   sql: `${CUBE.total} - ${CUBE.platformFee} - ${CUBE.stripeFee}`,
    //   type: `number`,
    //   format: `currency`
    // },

  },

  dimensions: {
    id: {
      sql: `id`,
      type: `number`,
      primaryKey: true
    },

    state: {
      sql: `state`,
      type: `string`
    },

    // https://cube.dev/docs/working-with-string-time-dimensions
    // https://stackoverflow.com/questions/45141426/how-to-get-average-between-two-dates-in-postgresql
    shippingTimeRange: {
      sql: `(LOWER(shipping_time_range) + (UPPER(shipping_time_range) - LOWER(shipping_time_range)) / 2)`,
      type: `time`
    },

    dayOfWeek: {
      // https://www.postgresql.org/docs/current/functions-formatting.html
      // ISO 8601 day of the week, Monday (1) to Sunday (7)
      sql: `TO_CHAR(${shippingTimeRange}, 'ID')`,
      type: `string`
    },

    hourRange: {
      // This will output ranges of 1 hour, like "10:00 - 11:00", "11:00 - 12:00", etc...
      sql: `LPAD(TO_CHAR(${shippingTimeRange}, 'HH24'), 2, '0') || ':00' || ' - ' || LPAD((TO_CHAR(${shippingTimeRange}, 'HH24')::numeric + 1)::text, 2, '0') || ':00'`,
      type: `string`
    },

    reusablePackagingEnabled: {
      sql: `reusable_packaging_enabled`,
      type: `boolean`
    },

    number: {
      sql: `number`,
      type: `string`
    },

    fulfillmentMethod: {
      type: `string`,
      case: {
        when: [
          {
            sql: `${CUBE}.takeaway = 't'`,
            label: `collection`,
          },
          {
            sql: `${CUBE}.takeaway = 'f'`,
            label: `delivery`,
          },
        ],
        else: { label: `delivery` },
      },
    },

    hasVendor: {
      type: `boolean`,
      sql: `${CUBE.vendorCount} > 0`,
      sub_query: true,
    },

    isMultiVendor: {
      type: `boolean`,
      sql: `${CUBE.vendorCount} > 1`,
      sub_query: true,
    },

    /*
    paymentMethod: {
      type: `string`,
      sql: `${CUBE.Payment.method}`,
    },
    */

    total: {
      sql: `ROUND(${CUBE}.total / 100::numeric, 2)`,
      type: `number`,
      format: `currency`
    },

    itemsTotal: {
      sql: `ROUND(${CUBE}.items_total / 100::numeric, 2)`,
      type: `number`,
      format: `currency`
    },

  },

  pre_aggregations: {
    // main: {
    //   measures: [
    //     Order.total,
    //     Order.itemsTotal,
    //     TaxAdjustment.totalAmount,
    //     OrderItem.total,
    //     TaxAdjustment.total_standard,
    //     TaxAdjustment.total_intermediary,
    //     TaxAdjustment.total_reduced,
    //     TipAdjustment.totalAmount,
    //     ReusablePackagingAdjustment.totalAmount,
    //     DeliveryAdjustment.totalAmount,
    //     StripeFee.totalAmount,
    //     PromotionAdjustment.totalAmount,
    //     PlatformFee.totalAmount,
    //     Refund.totalAmount
    //   ],
    //   dimensions: [
    //     Order.fulfillmentMethod,
    //     Order.hasVendor,
    //     Order.number,
    //     OrderVendor.name,
    //     Store.name
    //   ],
    //   timeDimension: OrderFulfilledEvent.createdAt,
    //   granularity: `day`
    // }
  },

  dataSource: `default`
});
