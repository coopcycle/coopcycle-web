cube(`OrderPerVendor`, {
  sql: `SELECT o.* FROM public.sylius_order o JOIN public.sylius_order_vendor v ON o.id = v.order_id WHERE ${SECURITY_CONTEXT.vendor_id.filter(
    'v.restaurant_id'
  )}`,
  extends: Order,
});
