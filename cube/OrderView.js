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
        // Measures
        `total`,
        `itemsTotal`,
        `itemsTaxTotal`,
        `deliveryFee`,
        `tip`,
        `packagingFee`,
        `promotions`,
        `stripeFee`,
        `platformFee`,
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
    }
  ]
})
