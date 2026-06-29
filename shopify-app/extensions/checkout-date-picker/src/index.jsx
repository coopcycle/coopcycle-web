import '@shopify/ui-extensions/preact';
import {render} from 'preact';
import {signal} from '@preact/signals';

const deliveryType = signal(null);
const slots = signal([]);
const selectedDate = signal('');
const selectedTime = signal('');

function getSelectedType(groups) {
  const group = groups?.[0];
  if (!group) return null;
  const handle = group.selectedDeliveryOption?.handle;
  return group.deliveryOptions?.find(o => o.handle === handle)?.type ?? null;
}

function generateSlots(spec) {
  const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
  const result = [];
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  for (let i = 1; i <= 14; i++) {
    const date = new Date(today);
    date.setDate(today.getDate() + i);
    const dayName = dayNames[date.getDay()];
    const dateStr = date.toISOString().split('T')[0];

    const times = [];
    for (const rule of spec) {
      if (Array.isArray(rule.dayOfWeek) && rule.dayOfWeek.includes(dayName)) {
        times.push({value: `${rule.opens} - ${rule.closes}`, label: `${rule.opens} - ${rule.closes}`});
      }
    }

    if (times.length > 0) {
      result.push({date: dateStr, times});
    }
  }

  return result;
}

export default async () => {
  const entry = shopify.appMetafields.value.find(e =>
    e.target.type === 'shop' &&
    e.metafield.namespace === 'coopcycle' &&
    e.metafield.key === 'slots_spec'
  );

  if (entry) {
    try {
      slots.value = generateSlots(JSON.parse(entry.metafield.value));
    } catch {
      slots.value = [];
    }
  }

  deliveryType.value = getSelectedType(shopify.deliveryGroups.value);
  shopify.deliveryGroups.subscribe(groups => {
    deliveryType.value = getSelectedType(groups);
  });

  render(<DatePicker />, document.body);
};

function DatePicker() {
  if (deliveryType.value !== 'local') return null;

  const currentSlots = slots.value;

  if (!currentSlots.length) {
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
