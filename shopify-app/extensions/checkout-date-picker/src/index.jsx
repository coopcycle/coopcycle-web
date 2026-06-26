import { useState, useEffect, useCallback } from "react";
import {
  reactExtension,
  useShop,
  useAppMetafields,
  useDeliveryGroups,
  useApplyAttributeChange,
  BlockStack,
  Heading,
  Select,
  Text,
  SkeletonText,
  Banner,
} from "@shopify/ui-extensions-react/checkout";

export default reactExtension("purchase.checkout.block.render", () => <DatePicker />);

function DatePicker() {
  const { myshopifyDomain } = useShop();
  const metafields = useAppMetafields();
  const deliveryGroups = useDeliveryGroups();
  const applyAttributeChange = useApplyAttributeChange();

  const [slots, setSlots] = useState(null);
  const [selectedDate, setSelectedDate] = useState("");
  const [selectedTime, setSelectedTime] = useState("");
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);

  const isLocalDelivery = deliveryGroups.some(
    (g) => g.selectedDeliveryOption?.type === "local"
  );

  const tenantUrl = metafields.find(
    (m) => m.metafield.namespace === "coopcycle" && m.metafield.key === "tenant_url"
  )?.metafield.value;

  useEffect(() => {
    if (!tenantUrl || !isLocalDelivery) {
      setLoading(false);
      return;
    }

    setLoading(true);
    setError(false);

    fetch(
      `${tenantUrl}/api/shopify/slots?domain=${encodeURIComponent(myshopifyDomain)}`
    )
      .then((r) => {
        if (!r.ok) throw new Error(`HTTP ${r.status}`);
        return r.json();
      })
      .then((data) => {
        setSlots(data.slots ?? []);
        setLoading(false);
      })
      .catch(() => {
        setError(true);
        setLoading(false);
      });
  }, [tenantUrl, isLocalDelivery, myshopifyDomain]);

  const handleDateChange = useCallback(
    async (date) => {
      setSelectedDate(date);
      setSelectedTime("");
      await applyAttributeChange({ key: "Delivery Date", type: "updateAttribute", value: date });
      await applyAttributeChange({ key: "Delivery Time", type: "updateAttribute", value: "" });
    },
    [applyAttributeChange]
  );

  const handleTimeChange = useCallback(
    async (time) => {
      setSelectedTime(time);
      await applyAttributeChange({ key: "Delivery Time", type: "updateAttribute", value: time });
    },
    [applyAttributeChange]
  );

  if (!isLocalDelivery) return null;

  if (loading) return <SkeletonText lines={2} />;

  if (error) {
    return (
      <Banner status="critical">
        <Text>Unable to load delivery slots. Please contact the store.</Text>
      </Banner>
    );
  }

  if (!slots || slots.length === 0) {
    return (
      <Banner status="warning">
        <Text>No delivery slots are currently available. Please contact the store.</Text>
      </Banner>
    );
  }

  const dateOptions = [
    { value: "", label: "Select a date…" },
    ...slots.map((s) => ({ value: s.date, label: formatDate(s.date) })),
  ];

  const selectedSlot = slots.find((s) => s.date === selectedDate);
  const timeOptions = selectedSlot
    ? [
        { value: "", label: "Select a time slot…" },
        ...selectedSlot.times.map((t) => ({ value: t.value, label: t.label })),
      ]
    : [];

  return (
    <BlockStack spacing="base">
      <Heading level={2}>Choose a delivery date</Heading>
      <Select
        label="Delivery date"
        options={dateOptions}
        value={selectedDate}
        onChange={handleDateChange}
      />
      {selectedDate && (
        <Select
          label="Time slot"
          options={timeOptions}
          value={selectedTime}
          onChange={handleTimeChange}
        />
      )}
    </BlockStack>
  );
}

function formatDate(dateStr) {
  // dateStr is "YYYY-MM-DD" — append T00:00:00 to avoid UTC offset shifting the day
  return new Date(`${dateStr}T00:00:00`).toLocaleDateString(undefined, {
    weekday: "long",
    month:   "long",
    day:     "numeric",
  });
}
