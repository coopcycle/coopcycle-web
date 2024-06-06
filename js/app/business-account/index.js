import Switch from '../widgets/Switch'

function bootstrap(targetEl) {

  if (!targetEl) {
    return
  }

  const targetSelector = targetEl.dataset.switchTarget;
  const el = document.querySelector(`[name="${targetSelector}"]`)

  new Switch(targetEl, {
    checked: el.checked,
    onChange: function(checked) {
      el.checked = checked

      const url = new URL(window.location.href);
      url.searchParams.set('_business', checked);
      window.location.replace(url.toString());
    }
  });

}

bootstrap(document.querySelector('[data-widget="business-mode-switch"]'))
