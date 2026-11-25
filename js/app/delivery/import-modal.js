/*
 * The import modal, where a dispatcher says what the file they are uploading holds.
 *
 * A delivery import wants a store, a task import wants a date: only the fields of the
 * kind that is chosen are shown, and only those are checked on the server. No-ops on the
 * screens that import deliveries only, which have no kind to choose.
 */

const MODAL_SELECTOR = '#import-modal'
const KIND_SELECTOR = '[data-import-kind] input[type="radio"]'

function showFieldsOf(kind, root) {
  root
    .querySelectorAll('[data-import-kind-field], [data-import-kind-link]')
    .forEach(el => {
      const belongsTo =
        el.dataset.importKindField || el.dataset.importKindLink
      el.classList.toggle('d-none', belongsTo !== kind)
    })
}

function chosenKind(radios) {
  const checked = radios.find(radio => radio.checked)

  return checked ? checked.value : null
}

export default function initImportModal() {
  const modal = document.querySelector(MODAL_SELECTOR)

  if (!modal) {
    return
  }

  const radios = Array.from(modal.querySelectorAll(KIND_SELECTOR))

  if (radios.length === 0) {
    return
  }

  const sync = () => {
    const kind = chosenKind(radios)
    if (kind) {
      showFieldsOf(kind, modal)
    }
  }

  radios.forEach(radio => radio.addEventListener('change', sync))

  // The form comes back with the kind that was submitted when it has errors, and the
  // modal is reopened to correct them
  $(modal).on('shown.bs.modal', sync)

  sync()
}
