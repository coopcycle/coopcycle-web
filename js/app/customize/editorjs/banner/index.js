import React, { useEffect, useState, createRef } from 'react'
import { createRoot } from 'react-dom/client'
import { ColorPicker } from 'antd';
import chroma from 'chroma-js'

import {
  MDXEditor,
  BlockTypeSelect, BoldItalicUnderlineToggles,
  headingsPlugin, toolbarPlugin,
} from '@mdxeditor/editor'

const Editor = ({ markdown, backgroundColor, onChangeColor, onChangeMarkdown }) => {

  const [className, setClassName] = useState('coopcycle-mdx-editor-dark')

  return (
    // https://mdxeditor.dev/editor/api/interfaces/MDXEditorProps
    <MDXEditor
      contentEditableClassName={className}
      markdown={markdown}
      plugins={[
        headingsPlugin(),
        toolbarPlugin({
          toolbarClassName: 'coopcycle-mdx-editor-toolbar',
          toolbarContents: () => (
            <>
              <BlockTypeSelect />
              <BoldItalicUnderlineToggles />
              <ColorPicker
                defaultValue={backgroundColor}
                showText
                size="small"
                onChange={(color, css) => {

                  const colorHex = color.toHexString();

                  // https://github.com/gka/chroma.js/issues/181
                  const colorScheme = chroma(colorHex).get('lab.l') < 70 ? 'dark' : 'light'
                  setClassName(`coopcycle-mdx-editor-${colorScheme}`)

                  onChangeColor(colorHex)
                }} />
            </>
          )
        }),
      ]}
      onChange={onChangeMarkdown} />
  )
}

export default class Banner {

  static get toolbox() {
    return {
      title: 'Banner',
      // https://lucide.dev/icons/rectangle-horizontal
      icon: '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-rectangle-horizontal-icon lucide-rectangle-horizontal"><rect width="20" height="12" x="2" y="6" rx="2"/></svg>'
    };
  }

  constructor({ data, config }) {

    console.log('CONSTRUCTOR', data)

    this.config = config;
    this.backgroundColor = data?.backgroundColor || '#1677ff'
    this.markdown = data?.markdown ? JSON.parse(data?.markdown) : '# Hello World'
    // this.markdown = data?.markdown || '# Hello World'
  }

  render() {

    const wrapper = document.createElement('div');
    wrapper.style.marginBottom = '20px'

    const bannerEl = document.createElement('div');
    bannerEl.style.backgroundColor = this.backgroundColor
    bannerEl.style.width = '100%'
    bannerEl.style.minHeight = '80px'

    const colorPickerEl = document.createElement('span')
    colorPickerEl.style.position = 'absolute'
    colorPickerEl.style.top = '10px'
    colorPickerEl.style.right = '10px'

    const mdxEditorEl = document.createElement('div')
    createRoot(mdxEditorEl).render(<Editor
      markdown={this.markdown}
      backgroundColor={this.backgroundColor}
      onChangeColor={(color) => {
        bannerEl.style.backgroundColor = color
        this.backgroundColor = color
      }}
      onChangeMarkdown={(markdown) => {
        // Make sure markdown is on a single line & escaped
        // FIXME Problem with save/reload
        // Unexpected token '#', "### Hello "... is not valid JSON
        this.markdown = JSON.stringify(markdown)
      }} />
    )
    bannerEl.appendChild(mdxEditorEl)

    wrapper.appendChild(bannerEl)

    return wrapper;
  }

  save(blockContent) {
    return {
      markdown: this.markdown,
      backgroundColor: this.backgroundColor,
    }
  }
}
