import Dropzone from 'dropzone'
import _ from 'lodash'
import i18n from '../i18n'
import croppieTransformFile from '../dropzone/croppie'

import "dropzone/dist/dropzone.css";

Dropzone.autoDiscover = false

export default function(el, options) {

  const [ width, height ] = options.size

  const croppieFormat = Object.prototype.hasOwnProperty.call(options, 'croppie') ? options.croppie.format : 'jpeg'
  const croppieEnableResize = Object.prototype.hasOwnProperty.call(options, 'croppie') ? options.croppie.enableResize : false

  const addRemoveLinks = Object.prototype.hasOwnProperty.call(options.dropzone, 'addRemoveLinks') ? options.dropzone.addRemoveLinks : false
  const deleteOthersAfterUpload =
    Object.prototype.hasOwnProperty.call(options.dropzone, 'deleteOthersAfterUpload') ? options.dropzone.deleteOthersAfterUpload : true

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
    dictDefaultMessage: `
      <span>
        <span class="d-block text-center mb-2">
          <img src="https://placehold.co/${Math.round(width / 6)}x${Math.round(height / 6)}?text=${options.imageType ? (options.imageType + '%0A') : ''}${width}x${height}" />
        </span>
        <span>${i18n.t('DROPZONE_DEFAULT_MESSAGE')}</span>
      </span>`,
    dictRemoveFile: i18n.t('DROPZONE_REMOVE_FILE'),
    addRemoveLinks,
    init: function() {

      var dz = this

      // Remove other thumbnails on upload success
      if (deleteOthersAfterUpload) {
        this.on('success', function(file) {
          dz.files.forEach(oneFile => {
            if (oneFile !== file) {
              dz.removeFile(oneFile)
            }
          })
        })
      }

      // @see https://github.com/enyo/dropzone/wiki/FAQ#how-to-show-files-already-stored-on-server
      const images = options.images && Array.isArray(options.images) ?
        options.images : ([ options.image ] || [])

      _.filter(images, image => !_.isEmpty(image)).forEach(image => {
        $.ajax({
          type: 'HEAD',
          async: true,
          url: image,
          success: function(message, text, jqXHR) {
            const filesize = jqXHR.getResponseHeader('Content-Length')
            const filename = image.substr(image.lastIndexOf('/') + 1)
            dz.displayExistingFile({ name: filename, size: filesize }, image)
          }
        })
      })

      if (options.dropzone.init && typeof options.dropzone.init === 'function') {
        options.dropzone.init.apply(dz)
      }

    },
    transformFile: croppieTransformFile(width, height, croppieFormat, croppieEnableResize),
  })
}
