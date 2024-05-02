import i18n from '../i18n'

document.querySelectorAll('.delete-package').forEach((el) => {
    el.addEventListener('click', (e) => {

    if (!window.confirm(i18n.t('CONFIRM_DELETE_WITH_PLACEHOLDER', { object_name: e.target.dataset.packageName }))) {
      e.preventDefault()
      return
    }

    const jwtToken = document.head.querySelector('meta[name="application-auth-jwt"]').content
    const headers = {
      'Authorization': `Bearer ${jwtToken}`,
      'Accept': 'application/ld+json',
      'Content-Type': 'application/ld+json'
    }

    const url = window.Routing.generate('api_package_sets_delete_item', {
      id: e.target.dataset.pricingId,
    })

    fetch(url, {method: "DELETE", headers: headers}).then(
      function () { location.reload(); }
    );

  });
})