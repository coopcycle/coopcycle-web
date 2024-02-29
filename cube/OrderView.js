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
        `paymentMethod`,
        // Measures
        `itemsTaxTotal`,
        `deliveryFee`,
        `tip`,
        `packagingFee`,
        `promotions`,
        `stripeFee`,
        `platformFee`,
        `revenue`,
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
      join_path: Order.OrderItem,
      includes: [
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
    {
      join_path: Order.Payment,
      includes: [
        `refundTotal`
      ]
    },
  ]
})
