import Dropzone from 'dropzone'
import DropzoneWidget from '../widgets/Dropzone'

Dropzone.autoDiscover = false

$(function() {

  const formData = document.querySelector('#product-form-data')

  new DropzoneWidget($('#product-image-dropzone'), {
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
})

$('#product_reusablePackagingEnabled').click(function() {
  if ($(this).is(":checked")) {
    $('#product_reusablePackagingUnit').closest('.form-group').show()
  } else {
    $('#product_reusablePackagingUnit').closest('.form-group').hide()
  }
})
