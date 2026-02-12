import React, { useEffect, useRef, forwardRef } from 'react'
import { Button, Flex } from 'antd';

import EditorJS from '@editorjs/editorjs';
import ShopCollection from './editorjs/shop-collection'

// https://dev.to/sumankalia/how-to-integrate-editorjs-in-reactjs-2l6l
const Editor = forwardRef(({ cuisines, shopTypes }, ref) => {

  // const ref = useRef();

  useEffect(() => {

    if (!ref.current) {

      console.log('Init EditorJS')

      const editor = new EditorJS({
        holder: 'editorjs',
        tools: {
          shop_collection: {
            class: ShopCollection,
            config: {
              cuisines,
              shopTypes,
            }
          }
        },
        autofocus: true,
        // Height of Editor's bottom area that allows to set focus on the last Block
        minHeight: 200,
        onReady: () => {
          ref.current = editor;
        },
        onChange: (api, event) => {
          editor.save()
            .then((savedData) => {
              console.log('SAVED', savedData);
            })
            .catch((error) => {
              console.log('EditorJS save error', error)
            })
        }
      });
    }

    return () => {
      ref?.current?.destroy();
      ref.current = null;
    };

  }, []);

  return (
    <div id="editorjs"></div>
  )
})

export default function({ cuisines, shopTypes }) {

  const ref = useRef();

  return (
    <div>
      <Editor ref={ref} cuisines={cuisines} shopTypes={shopTypes} />
      <Flex justify="flex-end">
        <Button type="primary" onClick={async () => {
          const data = await ref.current.save()
          console.log('Saving editor state', data)
        }}>Primary Button</Button>
      </Flex>
    </div>
  )
}
