import {
  extension,
  BlockStack,
  Heading,
  Select,
  Text,
  Banner,
  SkeletonText,
} from "@shopify/ui-extensions/checkout";

export default extension("purchase.checkout.block.render", async (root, api) => {
  const { settings, deliveryGroups, applyAttributeChange, shop } = api;

  let slots = null;
  let fetchDone = false;
  let selectedDate = "";
  let selectedTime = "";

  const block = root.createComponent(BlockStack, { spacing: "base" });
  root.append(block);

  function isLocal() {
    return (deliveryGroups.current ?? []).some(
      (g) => g.selectedDeliveryOption?.type === "local"
    );
  }

  function clear() {
    for (const child of [...block.children]) child.remove();
  }

  function render() {
    clear();

    if (!isLocal()) return;

    if (!fetchDone) {
      block.append(root.createComponent(SkeletonText, { lines: 2 }));
      return;
    }

    if (!slots || slots.length === 0) {
      const b = root.createComponent(Banner, { status: "warning" });
      b.append(
        root.createComponent(
          Text,
          {},
          "No delivery slots available. Please contact the store."
        )
      );
      block.append(b);
      return;
    }

    block.append(
      root.createComponent(Heading, { level: 2 }, "Choose a delivery date")
    );

    const dateOptions = [
      { value: "", label: "Select a date…" },
      ...slots.map((s) => ({ value: s.date, label: formatDate(s.date) })),
    ];

    block.append(
      root.createComponent(Select, {
        label: "Delivery date",
        options: dateOptions,
        value: selectedDate,
        onChange: async (date) => {
          selectedDate = date;
          selectedTime = "";
          await applyAttributeChange({
            key: "Delivery Date",
            type: "updateAttribute",
            value: date,
          });
          await applyAttributeChange({
            key: "Delivery Time",
            type: "updateAttribute",
            value: "",
          });
          render();
        },
      })
    );

    if (selectedDate) {
      const slot = slots.find((s) => s.date === selectedDate);
      const timeOptions = slot
        ? [{ value: "", label: "Select a time slot…" }, ...slot.times]
        : [];

      block.append(
        root.createComponent(Select, {
          label: "Time slot",
          options: timeOptions,
          value: selectedTime,
          onChange: async (time) => {
            selectedTime = time;
            await applyAttributeChange({
              key: "Delivery Time",
              type: "updateAttribute",
              value: time,
            });
            render();
          },
        })
      );
    }
  }

  deliveryGroups.subscribe(() => render());

  render();

  const tenantUrl = settings.current?.tenant_url;
  if (tenantUrl) {
    try {
      const r = await fetch(
        `${tenantUrl}/api/shopify/slots?domain=${encodeURIComponent(
          shop.myshopifyDomain
        )}`
      );
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      const data = await r.json();
      slots = data.slots ?? [];
    } catch {
      slots = [];
    }
  }

  fetchDone = true;
  render();
});

function formatDate(dateStr) {
  return new Date(`${dateStr}T00:00:00`).toLocaleDateString(undefined, {
    weekday: "long",
    month: "long",
    day: "numeric",
  });
}
