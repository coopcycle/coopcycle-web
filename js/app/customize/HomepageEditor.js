import React, { useEffect, useRef, forwardRef, useState } from 'react'
import { Button, Flex } from 'antd';
import { EyeOutlined } from '@ant-design/icons';
import { useTranslation } from 'react-i18next';
import _ from 'lodash';

import EditorJS from '@editorjs/editorjs';
import ShopCollection from './editorjs/shop-collection'
import Banner from './editorjs/banner'
import Slider from './editorjs/slider'
import DeliveryForm from './editorjs/delivery-form'

// https://blog.bitsrc.io/4-ways-to-communicate-across-browser-tabs-in-realtime-e4f5f6cbedca
const channel = new BroadcastChannel('homepage-preview');

const updatePreview = _.debounce((data) => {
  console.log('Refreshing preview')
  channel.postMessage(data);
}, 500)

// https://dev.to/sumankalia/how-to-integrate-editorjs-in-reactjs-2l6l
const Editor = forwardRef(({ blocks, cuisines, shopTypes, uploadEndpoint, deliveryForms, shopCollections, t }, ref) => {

  useEffect(() => {

    if (!ref.current) {

      const editor = new EditorJS({
        placeholder: t('HOMEPAGE_EDITOR.placeholder'),
        holder: 'editorjs',
        tools: {
          shop_collection: {
            class: ShopCollection,
            config: {
              cuisines,
              shopTypes,
              customCollections: shopCollections,
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
        onChange: async (api, event) => {
          console.log('EditorJS changed', event)
          const data = await editor.save();
          updatePreview(data);
        },
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

export default function({ blocks, cuisines, shopTypes, uploadEndpoint, deliveryForms, shopCollections }) {

  const ref = useRef();
  const { t } = useTranslation();
  const [isLoading, setIsLoading] = useState(false)
  const [isPreviewEnabled, setIsPreviewEnabled] = useState(false)

  const httpClient = new window._auth.httpClient();

  useEffect(() => {
    async function doUpdatePreview() {
        const data = await ref.current.save()
        window.open('/fr/', '_blank').focus();
        setTimeout(() => updatePreview(data), 1000);
    }
    if (isPreviewEnabled) {
      doUpdatePreview();
    }
  }, [isPreviewEnabled])

  return (
    <div>
      <Editor ref={ref}
        blocks={blocks}
        cuisines={cuisines}
        shopTypes={shopTypes}
        uploadEndpoint={uploadEndpoint}
        deliveryForms={deliveryForms}
        shopCollections={shopCollections}
        t={t} />
      <Flex justify="flex-end" gap="small">
        <Button icon={<EyeOutlined />} onClick={() => {
          setIsPreviewEnabled(!isPreviewEnabled)
        }}>{ t(isPreviewEnabled ? 'HOMEPAGE_EDITOR.disable_preview' : 'HOMEPAGE_EDITOR.enable_preview') }</Button>
        <Button type="primary" loading={isLoading} onClick={async () => {
          setIsLoading(true)
          const data = await ref.current.save()
          const { response } = await httpClient.put('/api/ui/homepage/blocks', { blocks: data.blocks });
          setIsLoading(false)
        }}>{ t('SAVE_BUTTON') }</Button>
      </Flex>
    </div>
  )
}
