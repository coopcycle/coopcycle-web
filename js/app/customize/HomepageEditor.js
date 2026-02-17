import React, { useEffect, useRef, forwardRef } from 'react'
import { Button, Flex } from 'antd';
import { useTranslation } from 'react-i18next';

import EditorJS from '@editorjs/editorjs';
import ShopCollection from './editorjs/shop-collection'
import Banner from './editorjs/banner'
import Slider from './editorjs/slider'
import DeliveryForm from './editorjs/delivery-form'

// https://dev.to/sumankalia/how-to-integrate-editorjs-in-reactjs-2l6l
const Editor = forwardRef(({ blocks, cuisines, shopTypes, uploadEndpoint, ctaIcon, deliveryForms, t }, ref) => {

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
              ctaIcon,
            }
          },
          banner: Banner,
          slider: {
            class: Slider,
            config: {
              uploadEndpoint,
            }
          },
          delivery_form: {
            class: DeliveryForm,
            config: {
              forms: deliveryForms,
            }
          }
        },
        autofocus: false,
        // Height of Editor's bottom area that allows to set focus on the last Block
        minHeight: 200,
        onReady: () => {
          ref.current = editor;
        },
        data: {
          blocks,
        },
        // https://editorjs.io/i18n/
        i18n: t('HOMEPAGE_EDITOR', { returnObjects: true }),
        // onChange: (api, event) => {
        //   editor.save()
        //     .then((savedData) => {
        //       console.log('SAVED', savedData);
        //     })
        //     .catch((error) => {
        //       console.log('EditorJS save error', error)
        //     })
        // }
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

export default function({ blocks, cuisines, shopTypes, uploadEndpoint, ctaIcon, deliveryForms }) {

  const ref = useRef();
  const { t } = useTranslation();

  const httpClient = new window._auth.httpClient();

  return (
    <div>
      <Editor ref={ref}
        blocks={blocks}
        cuisines={cuisines}
        shopTypes={shopTypes}
        uploadEndpoint={uploadEndpoint}
        ctaIcon={ctaIcon}
        deliveryForms={deliveryForms}
        t={t} />
      <Flex justify="flex-end">
        <Button type="primary" onClick={async () => {
          const data = await ref.current.save()
          const { response } = await httpClient.put('/api/ui/homepage/blocks', { blocks: data.blocks });
          console.log(response)
        }}>Save</Button>
      </Flex>
    </div>
  )
}
