import Dropzone from 'dropzone'
import DropzoneWidget from '../widgets/Dropzone'
import Sortable from 'sortablejs'

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
        }
      },
      image: formData.dataset.productImage,
      size: [ 256, 256 ]
    })
  }
})

$('#product_reusablePackagingEnabled').click(function() {
  if ($(this).is(":checked")) {
    $('#product_reusablePackaging').closest('.form-group').show()
    $('#product_reusablePackagingUnit').closest('.form-group').show()
  } else {
    $('#product_reusablePackaging').closest('.form-group').hide()
    $('#product_reusablePackagingUnit').closest('.form-group').hide()
  }
})

if (!$('#product_reusablePackagingEnabled').is(":checked")) {
  $('#product_reusablePackaging').closest('.form-group').hide()
  $('#product_reusablePackagingUnit').closest('.form-group').hide()
}

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
