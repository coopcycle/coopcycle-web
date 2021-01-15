import Croppie from 'croppie'
import i18n from '../i18n'

import "croppie/croppie.css";

// @see https://itnext.io/integrating-dropzone-with-javascript-image-cropper-optimise-image-upload-e22b12ac0d8a
export default function (width, height, croppieFormat, croppieEnableResize) {

  return function(file, done) {

    var dz = this

    // Create the image editor overlay
    var editor = document.createElement('div')
    editor.style.position = 'fixed'
    editor.style.left = 0
    editor.style.right = 0
    editor.style.top = 0
    editor.style.bottom = 0
    editor.style.zIndex = 9999
    editor.style.paddingTop = '100px'
    editor.style.paddingBottom = '100px'
    editor.style.backgroundColor = '#fff'

    document.body.appendChild(editor)

    // Create confirm button at the top left of the viewport
    var buttonConfirm = document.createElement('button')
    buttonConfirm.style.position = 'absolute'
    buttonConfirm.style.left = '10px'
    buttonConfirm.style.top = '10px'
    buttonConfirm.style.zIndex = 9999
    buttonConfirm.innerHTML = '<i class="fa fa-check"></i> ' + i18n.t('CROPPIE_CONFIRM')

    buttonConfirm.classList.add('btn')
    buttonConfirm.classList.add('btn-success')

    editor.appendChild(buttonConfirm)

    var buttonClose = document.createElement('button')

    buttonClose.style.position = 'absolute'
    buttonClose.style.right = '10px'
    buttonClose.style.top = '10px'
    buttonClose.style.zIndex = 9999
    buttonClose.textContent = '×'
    buttonClose.innerHTML = '<i class="fa fa-close"></i> ' + i18n.t('CROPPIE_CANCEL')

    buttonClose.classList.add('btn')
    buttonClose.classList.add('btn-danger')

    editor.appendChild(buttonClose)

    buttonClose.addEventListener('click', function() {
      dz.removeFile(file)
      document.body.removeChild(editor)
    })

    buttonConfirm.addEventListener('click', function() {

      let croppieOptions = {
        type: 'blob',
        format: croppieFormat
      }

      if (!croppieEnableResize) {
        croppieOptions = {
          size: {
            width: width,
            height: height
          },
          ...croppieOptions
        }
      }

      // Get the output file data from Croppie
      croppie.result(croppieOptions).then(function(blob) {

        // Create a new Dropzone file thumbnail
        dz.createThumbnail(
          blob,
          dz.options.thumbnailWidth,
          dz.options.thumbnailHeight,
          dz.options.thumbnailMethod,
          false,
          function(dataURL) {

            // Update the Dropzone file thumbnail
            dz.emit('thumbnail', file, dataURL)

            // Tell Dropzone of the new file
            done(blob)

          })
      })

      // Remove the editor from the view
      document.body.removeChild(editor)

    })

    // Create the Croppie editor
    var croppie = new Croppie(editor, {
      enableResize: croppieEnableResize,
      viewport: {
        width: width,
        height: height,
        type: 'square'
      }
    })

    // Tell Croppie to load the file
    croppie.bind({
      url: URL.createObjectURL(file)
    })

  }
}
