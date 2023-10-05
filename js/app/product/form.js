import Dropzone from 'dropzone'
import DropzoneWidget from '../widgets/Dropzone'
import Sortable from 'sortablejs'
import _ from 'lodash'
import numbro from 'numbro'
import { createStore } from 'redux'
import { createAction } from 'redux-actions'

import '../i18n'
import { calculate } from '../utils/tax'
import { openEditor } from './image-editor'

Dropzone.autoDiscover = false

$(function() {

  const el = document.querySelector('#product-image-dropzone')

  if (el) {
    const formData = document.querySelector('#product-form-data')
    new DropzoneWidget(el, {
      dropzone: {
        url: formData.dataset.actionUrl,
        params: {
          type: 'product',
          id: formData.dataset.productId
        },
        addRemoveLinks: true,
        deleteOthersAfterUpload: false,
        init: function() {
          this.on('removedfile', function(file) {
            $.ajax({
              url: window.location.pathname + '/images/' + file.name,
              type: 'DELETE',
            })
          })
        }
      },
      images: JSON.parse(formData.dataset.productImages),
      size: [ 256, 256 ]
    })
  }
})

const collectionHolder = document.querySelector('.reusablePackagings > ul');

$('#product_reusablePackagingEnabled').click(function() {
  if ($(this).is(":checked")) {
    $('.reusablePackagings').show()
    if (collectionHolder.querySelectorAll('li').length === 0) {
      addFormToCollection()
    }
  } else {
    $('.reusablePackagings').hide()
  }
})

if (!$('#product_reusablePackagingEnabled').is(':checked')) {
  $('.reusablePackagings').hide()
}

const addFormToCollection = () => {

  const item = document.createElement('li');

  item.innerHTML = collectionHolder
    .dataset
    .prototype
    .replace(
      /__name__/g,
      collectionHolder.dataset.index
    );

  collectionHolder.appendChild(item);

  collectionHolder.dataset.index++;
};

const addReusablePackaging = document.querySelector('.add_item_link')

if (addReusablePackaging) {
  addReusablePackaging.addEventListener("click", addFormToCollection)
}

$(document).on('click', '[data-reusable-packaging-delete]', function (e) {
  $(e.currentTarget).closest('li').remove()
})

new Sortable(document.querySelector('#product_options'), {
  group: 'products',
  animation: 250,
  onUpdate: function(e) {
    let i = 0
    Array.prototype.slice.call(e.to.children).forEach((el) => {
      const enabled = el.querySelector('input[type="checkbox"]')
      const pos = el.querySelector('[data-name="position"]')
      pos.value = enabled.checked ? i++ : -1
    })
  },
})

const getRateAmount = (el) => {

  const taxCategories = JSON.parse(el.dataset.taxCategories)
  const value = el.options[el.selectedIndex].value
  const rates = taxCategories[value]

  return _.reduce(rates, (acc, rate) => acc + rate.amount, 0)
}

document.querySelectorAll('[data-tax-categories]').forEach(el => {

  const taxIncl = JSON.parse(el.dataset.taxIncl)

  const taxIncludedEl = document.querySelector(el.dataset.included)
  const taxExcludedEl = document.querySelector(el.dataset.excluded)

  el.addEventListener('change', (e) => {

    const amount = getRateAmount(e.target)

    const masterEl = taxIncl ? taxIncludedEl : taxExcludedEl
    const otherEl  = taxIncl ? taxExcludedEl : taxIncludedEl

    const value = numbro.unformat(masterEl.value)

    const vatAmount = calculate((value * 100), amount, taxIncl)
    const otherValue = taxIncl ? ((value * 100) - vatAmount) : ((value * 100) + vatAmount)

    otherEl.value = numbro(otherValue / 100).format({ mantissa: 2 })
  })

  taxExcludedEl.addEventListener('input', (e) => {
    const value = numbro.unformat(e.target.value)

    if (!value || _.isNaN(value)) {
      taxIncludedEl.value = '0'
      return
    }

    const valueInCents = parseInt(value * 100, 10)
    const rateAmount = getRateAmount(el)

    const taxIncluded = valueInCents * (1 + rateAmount)

    taxIncludedEl.value = numbro(taxIncluded / 100).format({ mantissa: 2 })
  })

  taxIncludedEl.addEventListener('input', (e) => {
    const value = numbro.unformat(e.target.value)

    if (!value || _.isNaN(value)) {
      taxExcludedEl.value = '0'
      return
    }

    const valueInCents = parseInt(value * 100, 10)
    const rateAmount = getRateAmount(el)

    const vatAmount = Math.round(valueInCents - (valueInCents / (1 + rateAmount)))
    const taxExcluded = valueInCents - vatAmount

    taxExcludedEl.value = numbro(taxExcluded / 100).format({ mantissa: 2 })
  })
})

const SET_IMAGES = '@product/SET_IMAGES'
const setImages = createAction(SET_IMAGES)

const imageEditor = document.getElementById('image-editor')
const formData = document.querySelector('#product-form-data')

if (imageEditor && formData) {

  const store = createStore((state = {}, action) => {

    switch (action.type) {
      case SET_IMAGES:

        return {
          ...state,
          images: action.payload,
        }
    }

    return state
  })

  store.dispatch(
    setImages(JSON.parse(formData.dataset.productImages))
  )

  imageEditor.addEventListener('click', function(e) {
    e.preventDefault()
    openEditor({
      existingImages: store.getState().images,
      actionUrl: formData.dataset.actionUrl,
      productId: formData.dataset.productId,
      onClose: (images) => store.dispatch(setImages(images)),
    })
  })
}
