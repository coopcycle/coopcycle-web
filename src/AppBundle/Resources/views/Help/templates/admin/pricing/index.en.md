Pricing
-------

You can create pricings from the admin dashboard : `Settings` > `Pricing` . Afterwards, when you create a `Store`, you will be able to associate it to a `Pricing` in order to calculate the prices of the delivery created for this store.

#### Create a pricing (admin only)

Each `Pricing` is composed of several `Rules`. A `Rule` is a set of conditions and a price. When creating a delivery, the rules are traversed in order, and the first one whose expression match the delivery is applied.

Supported criterion for rules :

- Length of delivery (in m)
- Weight to deliver (in g)
- Type of vehicle (`bike` or `cargo bike`)
- Zone of delivery address 

#### Assign a pricing to a store (admin only)

  Each `Store` has a `Pricing`, you can assign it:
  
   - at store creation
   - later, thanks to the `Manage` dropdown on the right of each store in the `Stores` tab of the admin panel. 