(function () {
  const el = document.getElementById('coopcycle-date-picker');
  if (!el) return;

  const tenantUrl = el.dataset.tenantUrl;
  const shopDomain = el.dataset.shopDomain;
  const slotLabel = el.dataset.dateLabel || 'Delivery slot';
  const unavailableMessage =
    el.dataset.unavailableMessage || 'Delivery scheduling is temporarily unavailable.';
  const placeholderLabel = el.dataset.placeholderLabel || 'Choose a delivery slot';

  if (!tenantUrl || !shopDomain) {
    showUnavailable('missing_tenant_url');
    return;
  }

  Promise.all([
    fetch(`${tenantUrl}/api/shopify/slots?domain=${encodeURIComponent(shopDomain)}`).then(r => {
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      return r.json();
    }),
    fetch('/cart.js').then(r => r.json()),
  ])
    .then(([payload, cart]) => {
      const slots = payload && payload.slots;
      if (!slots || slots.length === 0) {
        showUnavailable((payload && payload.reason) || 'no_slots');
        return;
      }
      render(slots, cart.attributes);
    })
    .catch(err => showUnavailable(`request_failed: ${err.message}`));

  /**
   * Shoppers get a neutral message — the real cause is a shop-configuration
   * problem they can do nothing about, and naming it to them would be noise.
   * Whoever is setting the shop up gets the cause in the console, which is the
   * difference between a five-minute fix and an afternoon of guessing.
   */
  function showUnavailable(reason) {
    console.warn(
      `[CoopCycle] Delivery slots unavailable (${reason}). ` +
        'Check that the Shopify shop is linked to a CoopCycle store and that the store has a time slot configured.'
    );

    el.innerHTML = `<p class="coopcycle-error">${esc(unavailableMessage)}</p>`;
    el.style.display = '';
  }

  function render(slots, savedAttributes) {
    const savedDate = savedAttributes['Delivery Date'] ?? '';
    const savedTime = savedAttributes['Delivery Time'] ?? '';

    // Flatten slots into a single list of date+time options.
    const options = [];
    slots.forEach(s => {
      s.times.forEach(t => {
        options.push({ date: s.date, time: t.value, label: `${formatDate(s.date)}, ${t.label}` });
      });
    });

    const savedValue = savedDate && savedTime ? `${savedDate}|${savedTime}` : '';

    el.innerHTML = `
      <div class="coopcycle-picker">
        <div class="coopcycle-field">
          <label for="coopcycle-slot">${esc(slotLabel)}</label>
          <span class="coopcycle-select">
            <select id="coopcycle-slot">
              <option value="">${esc(placeholderLabel)}</option>
              ${options.map(o => {
                const val = esc(`${o.date}|${o.time}`);
                return `<option value="${val}" ${`${o.date}|${o.time}` === savedValue ? 'selected' : ''}>${esc(o.label)}</option>`;
              }).join('')}
            </select>
          </span>
        </div>
      </div>
    `;

    el.querySelector('#coopcycle-slot').addEventListener('change', function () {
      const [date, time] = this.value ? this.value.split('|') : ['', ''];
      updateCart(date ?? '', time ?? '');
    });

    el.style.display = '';
  }

  function updateCart(date, time) {
    fetch('/cart.js')
      .then(r => r.json())
      .then(cart => {
        const attributes = Object.assign({}, cart.attributes, {
          'Delivery Date': date,
          'Delivery Time': time,
        });
        return fetch('/cart/update.js', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ attributes }),
        });
      });
  }

  function formatDate(dateStr) {
    return new Date(`${dateStr}T00:00:00`).toLocaleDateString(undefined, {
      weekday: 'long',
      month: 'long',
      day: 'numeric',
    });
  }

  function esc(str) {
    return String(str)
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }
})();
