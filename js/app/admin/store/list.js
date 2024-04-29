import i18n from '../../i18n'

document.querySelectorAll('.delete-store').forEach((el) => {
    el.addEventListener('click', (e) => {

    if (!window.confirm(i18n.t('CONFIRM_DELETE_WITH_PLACEHOLDER', { object_name: e.target.dataset.storeName }))) {
      e.preventDefault()
      return
    }

    const jwtToken = document.querySelector("#stores-list").dataset.jwt
    const headers = {
      'Authorization': `Bearer ${jwtToken}`,
      'Accept': 'application/ld+json',
      'Content-Type': 'application/ld+json'
    }

    const url = window.Routing.generate('api_stores_get_item', {
      id: e.target.dataset.storeId,
    })

    fetch(url, {method: "DELETE", headers: headers}).then(
      function () { location.reload(); }
    );

  });
})
