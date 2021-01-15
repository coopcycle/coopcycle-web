import React, { useState, useEffect } from 'react'
import { render, unmountComponentAtNode } from 'react-dom'
import Dropzone from 'react-dropzone'
import Cropper from 'react-cropper'
import axios from 'axios'
import _ from 'lodash'
import classNames from 'classnames'
import basename from 'locutus/php/filesystem/basename'
import Modal from 'react-modal'
import { Progress } from 'antd'

import './image-editor.scss'
import 'cropperjs/dist/cropper.css'

// https://itnext.io/integrating-dropzone-with-javascript-image-cropper-optimise-image-upload-e22b12ac0d8a

const Navbar = ({ onClose, onClickTab }) => (
  <nav className="d-flex align-items-center border-bottom justify-content-around p-4 image-editor-nav">
    <a href="#" onClick={ e => {
      e.preventDefault()
      onClickTab('gallery')
    }}>
      <i className="fa fa-picture-o mr-2"></i>
      <span>Gallery</span>
    </a>
    <a href="#" onClick={ e => {
      e.preventDefault()
      onClickTab('upload')
    }}>
      <i className="fa fa-upload mr-2"></i>
      <span>Upload</span>
    </a>
    <a href="#" onClick={ e => {
      e.preventDefault()
      onClose()
    }} className="image-editor-close">
      <i className="fa fa-2x fa-times"></i>
    </a>
  </nav>
)

const NavBtn = ({ ratio, size, onClick, canvas, selected }) => (
  <a href="#" className={ classNames({
    'border': true,
    'border-primary': selected,
    'p-4': true,
    'd-block': true,
    'mb-4': true,
    'text-center': true }) } style={{ maxWidth: '50%' }} onClick={ e => {
      e.preventDefault()
      onClick()
    }}>
    <img style={{ width: '100%' }} src={ canvas ? canvas.toDataURL() : `//via.placeholder.com/${size.join('x')}/e5e5e5?text=${ratio}` } />
  </a>
)

const Gallery = ({ images, onDelete }) => (
  <div className="image-editor-gallery">
    { _.map(images, (image, key) => (
      <div key={ `product-image-${key}` } className={ classNames({
        'image-editor-gallery-item': true,
        'image-editor-gallery-item-ratio-16x9': image.ratio === '16:9'
      }) }>
        <img key={ `product-image-${key}` } src={ image.thumbnail } style={{ width: '100%' }} />
        <a href="#" className="image-editor-gallery-item-remove" onClick={ e => {
          e.preventDefault()
          onDelete(image)
        }}>
          <i className="fa fa-2x fa-times-circle"></i>
        </a>
      </div>
    )) }
  </div>
)

const ratios = {
  '1:1':  [ 256, 256 ],
  '16:9': [ 640, 360 ],
  // '4:3':  [ 512, 384 ],
}

const ratioToFloat = (ratio) => {
  const [ width, height ] = ratio.split(':').map(val => parseInt(val, 10))
  return width / height
}

const initialCanvases = {
  '1:1':  null,
  '16:9': null,
  '4:3':  null,
}

const Editor = ({ onClose, actionUrl, productId, existingImages }) => {

  const [ file, setFile ] = useState(null)
  const [ cropper, setCropper ] = useState(null)
  const [ ratio, setRatio ] = useState('1:1')
  const [ canvases, setCanvases ] = useState(initialCanvases)

  const [ images, setImages ] = useState(existingImages)
  const [ tab, setTab ] = useState('gallery')

  const [ uploadProgress, setUploadProgress ] = useState(0)
  const [ modalVisible, setModalVisible ] = useState(false)

  useEffect(() => {
    cropper && cropper.setAspectRatio(ratioToFloat(ratio))
  }, [ ratio ])

  const onCrop = () => {

    const [ width, height ] = ratios[ratio]

    const canvas = cropper.getCroppedCanvas({
      width,
      height,
    })

    setCanvases({
      ...canvases,
      [ ratio ]: canvas,
    })
  }

  // https://react-dropzone-uploader.js.org/docs/why-rdu#rdu-vs-react-dropzone
  // https://gist.github.com/virolea/e1af9359fe071f24de3da3500ff0f429
  const upload = () => {

    const cropped = _.pickBy(canvases, canvas => canvas !== null)

    const blobs = _.map(cropped, canvas => {
      return new Promise(resolve => {
        canvas.toBlob(blob => resolve(blob), 'image/jpeg')
      })
    })

    // https://github.com/axios/axios/blob/master/examples/upload/index.html
    const onUploadProgress = (progressEvent) => {
      const percentCompleted = Math.round((progressEvent.loaded * 100) / progressEvent.total)
      setUploadProgress(percentCompleted)
    }

    Promise.all(blobs)
      .then(values => {

        const files = _.zipObject(_.keys(cropped), values)

        const uploaders = _.map(files, (file, ratio) => {

          const formData = new FormData()
          formData.append('file', file, 'thumbnail.jpeg')
          formData.append('type', 'product')
          formData.append('id', productId)
          formData.append('ratio', ratio)

          return axios.post(actionUrl, formData, {
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            onUploadProgress,
          })
        })

        setModalVisible(true)

        Promise.all(uploaders).then(() => {

          setModalVisible(false)
          setUploadProgress(0)

          const newImages = _.map(files, (file, ratio) => ({
            ratio,
            thumbnail: URL.createObjectURL(file)
          }))

          setCanvases(initialCanvases)
          setFile(null)

          setImages(images.concat(newImages))
          setTab('gallery')

        })
      })
  }

  const disabled = (canvases['1:1'] === null)

  return (
    <div className="d-flex flex-column w-100 image-editor">
      <Navbar onClose={ () => onClose(images) } onClickTab={ tab => setTab(tab) } />
      <div className="d-flex w-100 h-100 overflow-auto">
        { tab === 'gallery' && (
          <Gallery images={ images } onDelete={ image => {
            $.ajax({
              url: window.location.pathname + '/images/' + basename(image.thumbnail),
              type: 'DELETE',
            }).then(() => setImages(_.without(images, image)))
          } } />
        ) }
        { tab === 'upload' && (
          <React.Fragment>
            <div className="w-25 border-right d-flex flex-column justify-content-between">
              <div className="d-flex flex-column align-items-center p-4">
                { _.map(ratios, (size, key) => (
                  <NavBtn
                    key={ key }
                    ratio={ key }
                    size={ size }
                    onClick={ () => setRatio(key) }
                    canvas={ canvases[key] }
                    selected={ key === ratio } />
                )) }
              </div>
              <div className="p-4">
                <button type="button" className="btn btn-block btn-lg btn-primary" disabled={ disabled } onClick={ upload }>Upload</button>
              </div>
            </div>
            <div className="w-75">
              { (tab === 'upload' && file === null) && (
                <Dropzone accept="image/*" maxFiles={ 1 } onDrop={ acceptedFiles => {
                  acceptedFiles.forEach(file => {
                    setFile(file)
                  })
                }}>
                  {({ getRootProps, getInputProps }) => (
                    <section className="d-flex align-items-center h-100">
                      <div { ...getRootProps() } className="d-flex w-100 h-100 align-items-center justify-content-center">
                        <input { ...getInputProps() } />
                        <span>Drag an image here, or click to select an image</span>
                      </div>
                    </section>
                  )}
                </Dropzone>
              )}
              { (tab === 'upload' && file !== null) && (
                <div className="image-editor-cropper">
                  <Cropper
                    src={ URL.createObjectURL(file) }
                    style={{ height: '100%', width: '100%' }}
                    initialAspectRatio={ ratioToFloat('1:1') }
                    aspectRatio={ ratioToFloat(ratio) }
                    guides={ true }
                    onInitialized={ cropper => setCropper(cropper) }
                    viewMode={ 1 /* restrict the crop box to not exceed the size of the canvas */ } />
                  <button className="btn btn-lg btn-success image-editor-cropper-crop-btn" onClick={ onCrop }>
                    <i className="fa fa-lg fa-crop mr-2"></i>
                    <span>Crop</span>
                  </button>
                </div>
              )}
            </div>
          </React.Fragment>
        ) }
      </div>
      <Modal
        isOpen={ modalVisible }
        style={{
          content: { minWidth: '33.3333%' },
          overlay: { zIndex: 4 }
        }}
        shouldCloseOnOverlayClick={ false }
        contentLabel={ 'Upload progress' }
        className="ReactModal__Content--restaurant">
        <Progress percent={ uploadProgress } status="active" />
      </Modal>
    </div>
  )
}

export function openEditor({ actionUrl, productId, existingImages, onClose }) {

  const editor = document.createElement('div')
  editor.style.position = 'fixed'
  editor.style.display = 'flex'
  editor.style.left = 0
  editor.style.right = 0
  editor.style.top = 0
  editor.style.bottom = 0
  editor.style.zIndex = 3
  editor.style.backgroundColor = '#fff'

  document.body.appendChild(editor)

  Modal.setAppElement(editor)

  render(<Editor
    existingImages={ existingImages }
    actionUrl={ actionUrl }
    productId={ productId }
    onClose={ (images) => {
      unmountComponentAtNode(editor)
      document.body.removeChild(editor)

      onClose(images)
    }} />, editor)
}
