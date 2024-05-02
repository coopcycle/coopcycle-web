import i18n from '../i18n'

document.querySelectorAll('.delete-pricing').forEach((el) => {
    el.addEventListener('click', (e) => {

    if (!window.confirm(i18n.t('CONFIRM_DELETE_WITH_PLACEHOLDER', { object_name: e.target.dataset.pricingName }))) {
      e.preventDefault()
      return
    }

    const jwtToken = document.querySelector("#pricings-list").dataset.jwt
    const headers = {
      'Authorization': `Bearer ${jwtToken}`,
      'Accept': 'application/ld+json',
      'Content-Type': 'application/ld+json'
    }

    const url = window.Routing.generate('api_pricing_rule_sets_delete_item', {
      id: e.target.dataset.pricingId,
    })

    fetch(url, {method: "DELETE", headers: headers}).then(
      function (resp) {
        if (resp.status === 400) {
          alert('there are objects linked to this pricing rule')
        } else {
          window.location.reload()
        }
      }
    );

  });
})