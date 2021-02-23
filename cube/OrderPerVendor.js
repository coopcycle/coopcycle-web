cube(`OrderPerVendor`, {
  sql: `SELECT * FROM public.sylius_order WHERE ${SECURITY_CONTEXT.vendor_id.filter(
    'vendor_id'
  )}`,
  extends: Order,
});
