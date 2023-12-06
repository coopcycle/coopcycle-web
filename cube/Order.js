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
    Adjustment: {
      relationship: `one_to_many`,
      sql: `${CUBE}.id = ${Adjustment}.order_id`,
    },
    DeliveryAdjustment: {
      relationship: `one_to_many`,
      sql: `${CUBE}.id = ${DeliveryAdjustment}.order_id`,
    },
    MessengerWithOrder: {
      relationship: `one_to_one`,
      sql: `${CUBE}.id = ${MessengerWithOrder}.id`,
    },
    Delivery: {
      relationship: `one_to_one`,
      sql: `${CUBE}.id = ${Delivery}.order_id`,
    },
    TipAdjustment: {
      relationship: `one_to_many`,
      sql: `${CUBE}.id = ${TipAdjustment}.order_id`,
    },
    ReusablePackagingAdjustment: {
      relationship: `one_to_many`,
      sql: `${CUBE}.id = ${ReusablePackagingAdjustment}.order_id`,
    },
    PromotionAdjustment: {
      relationship: `one_to_many`,
      sql: `${CUBE}.id = ${PromotionAdjustment}.order_id`,
    },
    StripeFee: {
      relationship: `one_to_many`,
      sql: `${CUBE}.id = ${StripeFee}.order_id`,
    },
    PlatformFee: {
      relationship: `one_to_many`,
      sql: `${CUBE}.id = ${PlatformFee}.order_id`,
    },
    OrderItem: {
      relationship: `one_to_many`,
      sql: `${CUBE}.id = ${OrderItem}.order_id`,
    },
  },

  measures: {
    count: {
      type: `count`,
      drillMembers: [id]
    },

    itemsTotal: {
      sql: `items_total`,
      type: `sum`
    },

    adjustmentsTotal: {
      sql: `adjustments_total`,
      type: `sum`
    },

    total: {
      sql: `ROUND(${CUBE}.total / 100::numeric, 2)`,
      type: `sum`,
      format: `currency`
    },

    averageTotal: {
      sql: `ROUND(${CUBE}.total / 100::numeric, 2)`,
      type: `avg`
    },

    deliveryFee: {
      sql: `${CUBE.DeliveryAdjustment.totalAmount}`,
      type: `number`,
    },

    vendorCount: {
      sql: `${CUBE.OrderVendor.count}`,
      type: `number`,
    },

    tip: {
      sql: `${CUBE.TipAdjustment.totalAmount}`,
      type: `number`,
    },

    packagingFee: {
      sql: `${CUBE.ReusablePackagingAdjustment.totalAmount}`,
      type: `number`,
    },

    promotions: {
      sql: `${CUBE.PromotionAdjustment.totalAmount}`,
      type: `number`,
    },

    stripeFee: {
      sql: `${CUBE.StripeFee.totalAmount}`,
      type: `number`,
    },

    platformFee: {
      sql: `${CUBE.PlatformFee.totalAmount}`,
      type: `number`,
    },

    itemsTaxTotal: {
      sql: `${CUBE.OrderItem.taxTotal}`,
      type: `number`,
    },

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
    }

  },

  dataSource: `default`
});
