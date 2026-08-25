(function () {
  const el = document.getElementById('coopcycle-date-picker');
  if (!el) return;

  const tenantUrl = el.dataset.tenantUrl;
  const shopDomain = el.dataset.shopDomain;

  if (!tenantUrl || !shopDomain) return;

  const slotLabel = el.dataset.dateLabel || 'Delivery slot';

  Promise.all([
    fetch(`${tenantUrl}/api/shopify/slots?domain=${encodeURIComponent(shopDomain)}`).then(r => {
      if (!r.ok) throw new Error(`HTTP ${r.status}`);
      return r.json();
    }),
    fetch('/cart.js').then(r => r.json()),
  ])
    .then(([{ slots }, cart]) => {
      if (!slots || slots.length === 0) return;
      render(slots, cart.attributes);
    })
    .catch(() => { /* silently hide on error */ });

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
          <select id="coopcycle-slot">
            <option value="">—</option>
            ${options.map(o => {
              const val = esc(`${o.date}|${o.time}`);
              return `<option value="${val}" ${`${o.date}|${o.time}` === savedValue ? 'selected' : ''}>${esc(o.label)}</option>`;
            }).join('')}
          </select>
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
