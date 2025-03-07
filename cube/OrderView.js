view(`OrderView`, {
  cubes: [
    {
      join_path: Order,
      includes: [
        // Dimensions
        `number`,
        `state`,
        `fulfillmentMethod`,
        `hasVendor`,
        `total`,
        `itemsTotal`,
      ]
    },
    {
      join_path: Order.OrderItemTaxAdjustment,
      includes: [
        {
          name: `tax_total`,
          alias: `itemsTaxTotal`
        },
        {
          name: `tax_total_standard`,
          alias: `items_tax_total_standard`
        },
        {
          name: `tax_total_intermediary`,
          alias: `items_tax_total_intermediary`
        },
        {
          name: `tax_total_reduced`,
          alias: `items_tax_total_reduced`
        },
        {
          name: `total_excl_tax_standard`,
          alias: `items_total_excl_tax_standard`
        },
        {
          name: `total_excl_tax_intermediary`,
          alias: `items_total_excl_tax_intermediary`
        },
        {
          name: `total_excl_tax_reduced`,
          alias: `items_total_excl_tax_reduced`
        },
        {
          name: `total_excl_tax`,
          alias: `items_total_excl_tax`
        },
      ]
    },
    {
      join_path: Order.OrderFee,
      includes: [
        {
          name: `delivery_fee`,
          alias: `deliveryFee`
        },
        {
          name: `stripe_fee`,
          alias: `stripeFee`
        },
        {
          name: `platform_fee`,
          alias: `platformFee`
        },
        {
          name: `packaging_fee`,
          alias: `packagingFee`
        },
        `tip`,
        `promotions`,
        `revenue`
      ]
    },
    {
      join_path: Order.OrderPayment,
      includes: [
        {
          name: `refund_total`,
          alias: `refundTotal`
        },
        {
          name: `method`,
          alias: `paymentMethod`
        },
      ]
    },
    {
      join_path: Order.OrderVendor,
      includes: [
        {
          name: `name`,
          alias: `vendorName`
        }
      ]
    },
    {
      join_path: Order.Delivery.Store,
      includes: [
        {
          name: `name`,
          alias: `storeName`
        }
      ]
    },
    {
      join_path: Order.OrderFulfilledEvent,
      includes: [
        {
          name: `createdAt`,
          alias: `completedAt`
        }
      ]
    },
    {
      join_path: Order.MessengerWithOrder,
      includes: [
        {
          name: `username`,
          alias: `completedBy`
        }
      ]
    },
    {
      join_path: Order.Delivery,
      includes: [
        {
          name: `distance`,
          alias: `delivery_distance`
        }
      ]
    },
  ]
})
