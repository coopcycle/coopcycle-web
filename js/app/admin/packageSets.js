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
      id: e.target.dataset.packageId,
    })

    fetch(url, {method: "DELETE", headers: headers}).then(
      function (resp) {
        if (resp.status === 400) {
          alert(i18n.t('ADMIN_PLEASE_UNLINK_PACKAGE_SET_BEFORE_DELETION'))
        } else {
          window.location.reload()
        }
      }
    );

  });
})