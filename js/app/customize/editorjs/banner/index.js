import React, { useEffect, useState, createRef } from 'react'
import { createRoot } from 'react-dom/client'
import chroma from 'chroma-js'
import ColorPicker from '../../color-picker';
import { Input, Space } from 'antd';
import { LinkOutlined } from '@ant-design/icons';

import {
  MDXEditor,
  BlockTypeSelect, BoldItalicUnderlineToggles,
  headingsPlugin, toolbarPlugin,
} from '@mdxeditor/editor'

const Editor = ({ markdown, backgroundColor, onChangeColor, onChangeMarkdown, colorScheme, link, onChangeLink }) => {

  const [className, setClassName] = useState(`coopcycle-mdx-editor-content coopcycle-homepage-bg-${colorScheme}`)

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
                onChange={(color, css, colorScheme) => {
                  const colorHex = color.toHexString();
                  setClassName(`coopcycle-mdx-editor-content coopcycle-homepage-bg-${colorScheme}`)
                  onChangeColor(colorHex, colorScheme)
                }} />
              <Input defaultValue={link} size="small" style={{ width: 170 }} prefix={<LinkOutlined />} onChange={onChangeLink} />
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
    this.config = config;
    this.backgroundColor = data?.backgroundColor || '#1677ff'
    try {
      this.markdown = JSON.parse(data?.markdown)
    } catch (e) {
      this.markdown = data?.markdown
    }
    this.markdown = this.markdown || '# Hello World'
    this.colorScheme = data.colorScheme || 'light'
    this.link = data.link || null
  }

  render() {

    const wrapper = document.createElement('div');
    wrapper.style.marginBottom = '20px'



    const bannerEl = document.createElement('div');
    bannerEl.style.backgroundColor = this.backgroundColor
    bannerEl.style.width = '100%'
    bannerEl.style.minHeight = '80px'

    const mdxEditorEl = document.createElement('div')
    createRoot(mdxEditorEl).render(<Editor
      markdown={this.markdown}
      backgroundColor={this.backgroundColor}
      colorScheme={this.colorScheme}
      link={this.link}
      onChangeColor={(color, colorScheme) => {
        bannerEl.style.backgroundColor = color
        this.backgroundColor = color
        this.colorScheme = colorScheme
      }}
      onChangeMarkdown={(markdown) => {
        // Make sure markdown is on a single line & escaped
        // FIXME Problem with save/reload
        // Unexpected token '#', "### Hello "... is not valid JSON
        this.markdown = JSON.stringify(markdown)
      }}
      onChangeLink={(e) => {
        this.link = e.target.value
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
      colorScheme: this.colorScheme,
      link: this.link,
    }
  }
}
