import dragula from 'dragula'

let containers = []

const productContainers = [].slice.call(document.querySelectorAll('[data-draggable-target]'))
containers = containers.concat(productContainers)

const sectionContainers = [].slice.call(document.querySelectorAll('.menuEditor__left'))
containers = containers.concat(sectionContainers)

const source = document.querySelector('[data-draggable-source]')
containers.push(source)

const childrenContainer = document.querySelector('#menu_editor_children')

function resolveProductInput(taxonId, productId) {
  const formContainer = childrenContainer
    .querySelector(`[data-taxon-id="${taxonId}"]`)
    .querySelector('[data-prototype]')
  return $(formContainer)
    .find('[name$="[product]"]')
    .filter((index, el) => $(el).val() === productId)
}

function reorderProducts(taxonId) {

  const drakeContainer = document
    .querySelector(`[data-draggable-target][data-taxon-id="${taxonId}"]`)

  const productPositions = [].slice.call(drakeContainer.children).map((el, index) => {
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

dragula(containers, {
  accepts: (el, target, source, sibling) => {

    if (el.classList.contains('menuEditor__panel')) {

      return target === source
    }

    return true
  }
})
  .on('drop', (el, target, source) => {

    // Sections have been reordered
    if (el.classList.contains('menuEditor__panel')) {
      reorderSections()

      return
    }

    // Products have been reordered in the same taxon
    if (target === source) {

      const taxonId = target.getAttribute('data-taxon-id')
      reorderProducts(taxonId)

      return
    }

    if (target.hasAttribute('data-draggable-target')) {

      const taxonId = target.getAttribute('data-taxon-id')
      const productId = el.getAttribute('data-product-id')

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

      // Product was moved from a taxon to another
      if (source.hasAttribute('data-draggable-target')) {

        const sourceTaxonId = source.getAttribute('data-taxon-id')

        removeProduct(sourceTaxonId, productId)
        reorderProducts(sourceTaxonId)
      }

    }

    if (target.hasAttribute('data-draggable-source')) {

      const taxonId = source.getAttribute('data-taxon-id')
      const productId = el.getAttribute('data-product-id')

      removeProduct(taxonId, productId)
      reorderProducts(taxonId)
    }

  })
