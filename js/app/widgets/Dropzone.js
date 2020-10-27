import Dropzone from 'dropzone'
import i18n from '../i18n'
import croppieTransformFile from '../dropzone/croppie'

import "dropzone/dist/dropzone.css";

Dropzone.autoDiscover = false

export default function(el, options) {

  const [ width, height ] = options.size

  const croppieFormat = Object.prototype.hasOwnProperty.call(options, 'croppie') ? options.croppie.format : 'jpeg'
  const croppieEnableResize = Object.prototype.hasOwnProperty.call(options, 'croppie') ? options.croppie.enableResize : false

  const $dropzoneContainer = $('<div>')
  $dropzoneContainer.addClass('dropzone')
  $dropzoneContainer.addClass('dropzone--blue')
  $dropzoneContainer.appendTo($(el))

  $dropzoneContainer.dropzone({
    url: options.dropzone.url,
    acceptedFiles: 'image/*',
    resizeMimeType: 'image/jpeg',
    thumbnailWidth: width,
    thumbnailHeight: height,
    params: options.dropzone.params,
    dictDefaultMessage: i18n.t('DROPZONE_DEFAULT_MESSAGE'),
    init: function() {

      var dz = this

      // Remove other thumbnails on upload success
      this.on('success', function(file) {
        dz.files.forEach(oneFile => {
          if (oneFile !== file) {
            dz.removeFile(oneFile)
          }
        })
      })

      // @see https://github.com/enyo/dropzone/wiki/FAQ#how-to-show-files-already-stored-on-server
      if (options.image) {
        $.ajax({
          type: 'HEAD',
          async: true,
          url: options.image,
          success: function(message, text, jqXHR) {
            const filesize = jqXHR.getResponseHeader('Content-Length')
            const filename = options.image.substr(options.image.lastIndexOf('/') + 1)
            dz.displayExistingFile({ name: filename, size: filesize }, options.image)
          }
        })
      }

    },
    transformFile: croppieTransformFile(width, height, croppieFormat, croppieEnableResize),
  })
}
