import '@shopify/ui-extensions/preact';
import {render} from 'preact';
import {signal} from '@preact/signals';

const deliveryType = signal(null);
const slots = signal(null);
const selectedDate = signal('');
const selectedTime = signal('');
const loading = signal(false);
const fetchError = signal(false);

function getSelectedType(groups) {
  const group = groups?.[0];
  if (!group) return null;
  const handle = group.selectedDeliveryOption?.handle;
  return group.deliveryOptions?.find(o => o.handle === handle)?.type ?? null;
}

export default async () => {
  deliveryType.value = getSelectedType(shopify.deliveryGroups.value);

  shopify.deliveryGroups.subscribe(groups => {
    deliveryType.value = getSelectedType(groups);
    if (deliveryType.value === 'local' && slots.value === null && !loading.value) {
      loadSlots();
    }
  });

  render(<DatePicker />, document.body);

  if (deliveryType.value === 'local') {
    loadSlots();
  }
};

async function loadSlots() {
  const tenantUrl = shopify.settings.value?.tenant_url;
  const domain = shopify.shop?.myshopifyDomain;

  if (!tenantUrl) return;

  loading.value = true;
  fetchError.value = false;

  try {
    const r = await fetch(
      `${tenantUrl}/api/shopify/slots?domain=${encodeURIComponent(domain ?? '')}`
    );
    if (!r.ok) throw new Error(`HTTP ${r.status}`);
    const data = await r.json();
    slots.value = data.slots ?? [];
  } catch {
    fetchError.value = true;
  } finally {
    loading.value = false;
  }
}

function DatePicker() {
  if (deliveryType.value !== 'local') return null;

  if (loading.value) return <s-skeleton-paragraph lines="2" />;

  if (fetchError.value) {
    return (
      <s-banner tone="critical">
        <s-text>Unable to load delivery slots. Please contact the store.</s-text>
      </s-banner>
    );
  }

  const currentSlots = slots.value;

  if (!currentSlots || currentSlots.length === 0) {
    return (
      <s-banner tone="warning">
        <s-text>No delivery slots available. Please contact the store.</s-text>
      </s-banner>
    );
  }

  const selectedSlot = currentSlots.find(s => s.date === selectedDate.value);

  return (
    <s-stack direction="column" gap="base">
      <s-heading level="2">Choose a delivery date</s-heading>
      <s-select
        label="Delivery date"
        value={selectedDate.value}
        onChange={async (e) => {
          const date = e.currentTarget.value;
          selectedDate.value = date;
          selectedTime.value = '';
          await shopify.applyAttributeChange({key: 'Delivery Date', type: 'updateAttribute', value: date});
          await shopify.applyAttributeChange({key: 'Delivery Time', type: 'updateAttribute', value: ''});
        }}
      >
        <s-option value="">Select a date…</s-option>
        {currentSlots.map(s => (
          <s-option key={s.date} value={s.date}>{formatDate(s.date)}</s-option>
        ))}
      </s-select>
      {selectedDate.value && selectedSlot && (
        <s-select
          label="Time slot"
          value={selectedTime.value}
          onChange={async (e) => {
            const time = e.currentTarget.value;
            selectedTime.value = time;
            await shopify.applyAttributeChange({key: 'Delivery Time', type: 'updateAttribute', value: time});
          }}
        >
          <s-option value="">Select a time slot…</s-option>
          {selectedSlot.times.map(t => (
            <s-option key={t.value} value={t.value}>{t.label}</s-option>
          ))}
        </s-select>
      )}
    </s-stack>
  );
}

function formatDate(dateStr) {
  return new Date(`${dateStr}T00:00:00`).toLocaleDateString(undefined, {
    weekday: 'long',
    month: 'long',
    day: 'numeric',
  });
}
