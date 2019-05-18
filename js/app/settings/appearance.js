import Dropzone from 'dropzone'
import DropzoneWidget from '../widgets/Dropzone'

Dropzone.autoDiscover = false

$(function() {

	const formData = document.querySelector('#appearance-settings-form-data')

	console.log(formData.dataset)

	new DropzoneWidget($('#logo-dropzone'), {
		dropzone: {
		  url: formData.dataset.actionUrl,
		  params: {
		    type: 'logo',
		  }
		},
		image: formData.dataset.logoImage,
		size: [ 256, 256 ],
		croppie: {
			format: 'png'
		}
	})
})
