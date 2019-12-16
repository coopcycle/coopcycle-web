import Sortable from 'sortablejs'

const childrenContainer = document.querySelector('#menu_editor_children')

let productContainers = [
  document.querySelector('[data-draggable-source]')
]
productContainers = productContainers.concat(
  [].slice.call(document.querySelectorAll('[data-draggable-target]'))
)

const sectionContainers = [].slice.call(document.querySelectorAll('.menuEditor__left'))

function resolveProductInput(taxonId, productId) {
  const formContainer = childrenContainer
    .querySelector(`[data-taxon-id="${taxonId}"]`)
    .querySelector('[data-prototype]')
  return $(formContainer)
    .find('[name$="[product]"]')
    .filter((index, el) => $(el).val() === productId)
}

function reorderProducts(taxonId) {

  const container = document
    .querySelector(`[data-draggable-target][data-taxon-id="${taxonId}"]`)

  const productPositions = [].slice.call(container.children).map((el, index) => {
    return {
      product: el.getAttribute('data-product-id'),
      position: (index + 1)
    }
  })

  productPositions.forEach(productPosition => {
    const productInput = resolveProductInput(taxonId, productPosition.product)
    if (productInput) {
      $(productInput)
        .closest('div')
        .find('[name$="[position]"]')
        .val(productPosition.position)
    }
  })

}

function resolveSectionInput(taxonId) {

  return childrenContainer
    .querySelector(`[data-taxon-id="${taxonId}"]`)
    .querySelector('[name$="[position]"]')
}

function reorderSections() {

  const sectionPositions = [].slice.call(document.querySelectorAll('.menuEditor__left .menuEditor__panel')).map((el, index) => {
    return {
      section: el.getAttribute('data-taxon-id'),
      position: index
    }
  })

  sectionPositions.forEach(sectionPosition => {
    const sectionInput = resolveSectionInput(sectionPosition.section)
    sectionInput.value = sectionPosition.position
  })
}

$('#editTaxonModal form').on('submit', function(e) {
  e.preventDefault()

  const taxonId = parseInt($(this).find('input[type="hidden"]').val(), 10)
  const taxonName = $(this).find('input[type="text"]').val()

  $(`[data-edit-taxon-id="${taxonId}"]`).text(taxonName)

  const input = childrenContainer
    .querySelector(`[data-taxon-id="${taxonId}"]`)
    .querySelector('input[type="text"]')

  input.value = taxonName

  $('#editTaxonModal').modal('hide')
})

$('#editTaxonModal').on('show.bs.modal', function (e) {

  const $trigger = $(e.relatedTarget)
  const $modal = $(this)

  const taxonId = $trigger.data('edit-taxon-id')

  const taxonName =
    childrenContainer
      .querySelector(`[data-taxon-id="${taxonId}"]`)
      .querySelector('input[type="text"]')
      .value

  $modal.find('.modal-body input[type="hidden"]').val(taxonId)
  $modal.find('.modal-body input[type="text"]').val(taxonName)
})

function removeProduct(taxonId, productId) {
  const productInput = resolveProductInput(taxonId, productId)
  if (productInput) {
    $(productInput).closest('div').remove()
  }
}

productContainers.forEach(container => {
  new Sortable(container, {
    group: 'products',
    animation: 250,
    onAdd: function(e) {
      if (e.to.hasAttribute('data-draggable-target')) {

        const taxonId = e.to.getAttribute('data-taxon-id')
        const productId = e.item.getAttribute('data-product-id')

        const formContainer = childrenContainer
          .querySelector(`[data-taxon-id="${taxonId}"]`)
          .querySelector('[data-prototype]')

        const prototype = formContainer.getAttribute('data-prototype')

        const index = $(formContainer).children().length
        const form = prototype.replace(/__taxonProducts__/g, index)

        const $form = $(form)

        $form
          .find('[name$="[product]"]')
          .val(productId)
        $form
          .find('[name$="[position]"]')
          .val(index + 1)

        $(formContainer).append($form)

        reorderProducts(taxonId)
      }
    },
    onUpdate: function(e) {
      if (e.to.hasAttribute('data-draggable-target')) {
        const taxonId = e.to.getAttribute('data-taxon-id')
        reorderProducts(taxonId)
      }
    },
    onRemove: function(e) {
      if (e.from.hasAttribute('data-draggable-target')) {
        const taxonId = e.from.getAttribute('data-taxon-id')
        const productId = e.item.getAttribute('data-product-id')

        removeProduct(taxonId, productId)
        reorderProducts(taxonId)
      }
    },
  })
})

sectionContainers.forEach(container => {
  new Sortable(container, {
    group: 'sections',
    animation: 250,
    onUpdate: function() {
      reorderSections()
    },
  })
})
