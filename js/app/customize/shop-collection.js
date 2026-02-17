import React, { useRef } from 'react';
import { Button, Flex, Select, Input } from 'antd';
import {
  CloseOutlined,
} from '@ant-design/icons';
import { createRoot } from 'react-dom/client'
import _ from 'lodash'

import { configureStore, createSlice, createAction, createReducer } from '@reduxjs/toolkit'
import { Provider, useSelector, useDispatch } from 'react-redux'

import { Navigation, Manipulation } from 'swiper/modules'
import { Swiper, SwiperSlide, useSwiper } from 'swiper/react'

import { useTranslation } from 'react-i18next';

import { accountSlice } from '../entities/account/reduxSlice';
import {
  apiSlice,
  useUpdateShopCollectionMutation,
  useCreateShopCollectionMutation,
  useDeleteShopCollectionMutation,
} from '../api/slice';

import 'swiper/css';
import 'swiper/css/navigation'

import './shop-collection.scss'
import '../restaurant/list.scss'
import 'skeleton-screen-css/dist/index.scss'

const initialState = {
  shops: [],
  collections: [],
}

const app = createSlice({
  name: 'app',
  initialState,
  reducers: {
    addSlide: (state, action) => {
      const index = _.findIndex(state.collections, (c) => c['@id'] === action.payload.collection['@id'])
      state.collections[index].shops.splice(state.collections[index].shops.length, 0, null);
    },
    updateSlide: (state, action) => {
      const index = _.findIndex(state.collections, (c) => c['@id'] === action.payload.collection['@id'])
      state.collections[index].shops.splice(action.payload.index, 1, action.payload.value);
    },
    removeSlide: (state, action) => {
      const index = _.findIndex(state.collections, (c) => c['@id'] === action.payload.collection['@id'])
      state.collections[index].shops.splice(action.payload.index, 1);
    },
    setTitle: (state, action) => {
      const index = _.findIndex(state.collections, (c) => c['@id'] === action.payload.collection['@id'])
      state.collections[index].title = action.payload.title
    },
    addCollection: (state, action) => {
      state.collections.push(action.payload)
    },
    removeCollection: (state, action) => {
      const index = _.findIndex(state.collections, (c) => c['@id'] === action.payload['@id'])
      state.collections.splice(index, 1)
    }
  },
})

const ShopSelect = ({ collection, shop, index }) => {

  const dispatch = useDispatch()
  const shops = useSelector((state) => state.app.shops)

  const options = shops.map(s => ({ label: s.name, value: s['@id'] }))

  return (
    <Select
      allowClear
      showSearch
      placeholder="Select a shop"
      filterOption={(input, option) =>
        (option?.label ?? '').toLowerCase().includes(input.toLowerCase())
      }
      options={options}
      value={shop ?? null}
      styles={{
        root: {
          width: '100%'
        }
      }}
      onChange={(value) => {
        dispatch(app.actions.updateSlide({
          collection,
          index,
          value
        }))
      }}
    />
  )
}

const ShopPreview = ({ collection, shop, index, onClickDelete }) => {
  return (
    <div className="restaurant-item">
      <a>
        <div className="restaurant-image">
          <div className="restaurant-banner">
            <img src="//placehold.co/480x270" />
          </div>
          <div className="restaurant-logo">
            <ShopLogo shop={shop} />
          </div>
          <div className="badges"></div>
        </div>
        <div className="restaurant-details">
          <div className="restaurant-item__title">
            <ShopSelect collection={collection} shop={shop} index={index} />
          </div>
          <div>
            <div className="ssc">
              <div className="ssc-line w-40"></div>
            </div>
          </div>
          <div className="details">
            <div className="ssc w-100">
              <div className="ssc-line w-100"></div>
            </div>
          </div>
        </div>
        <span className="top-right">
          <Button type="link" icon={<CloseOutlined />} onClick={onClickDelete} />
        </span>
      </a>
  </div>
  )
}

const ShopLogo = ({ shop }) => {

  const image = useSelector((state) => {
    const s = _.find(state.app.shops, (s) => s['@id'] === shop)
    return s?.image
  })

  if (!image) {
    return (
      <img src="//placehold.co/512x512" />
    )
  }

  return (
    <img src={image} />
  )
}

const CollectionSwiper = ({ collection }) => {

  const dispatch = useDispatch()
  const { t } = useTranslation();

  return (
    <Swiper
      modules={[
        Navigation
      ]}
      spaceBetween={20}
      slidesPerView={4}
      navigation
      // https://stackoverflow.com/questions/75958722/react-select-does-not-work-in-swiper-component
      // https://github.com/nolimits4web/swiper/issues/1152
      preventClicks={false}
      simulateTouch={false}
    >
      <div slot="container-start" className="mb-2">
        <Input value={collection.title} style={{ width: 200 }} onChange={(e) => {
          dispatch(app.actions.setTitle({ collection, title: e.target.value }))
        }} />
      </div>
      { collection.shops.map((shop, index) => (
        <SwiperSlide key={`shop-${index}`}>
          <ShopPreview
            collection={collection}
            shop={shop}
            index={index}
            onClickDelete={() => dispatch(app.actions.removeSlide({ collection, index }))} />
        </SwiperSlide>
      )) }
      <div slot="container-end" className="pt-2">
        <Flex justify="space-between" align="center">
          <span className="help-block">
            <i className="fa fa-info-circle mr-2"></i>
            <span>{t('SHOP_COLLECTIONS.SORT_DISCLAIMER')}</span>
          </span>
          <Flex justify="flex-end" gap="small">
            <DeleteButton collection={ collection } />
            <AddButton collection={ collection } />
            <SaveButton collection={ collection } />
          </Flex>
        </Flex>
      </div>
    </Swiper>
  )
}

const AddButton = ({ collection }) => {

  const { t } = useTranslation();

  const dispatch = useDispatch()
  const swiper = useSwiper();

  return (
    <Button onClick={() => {
      dispatch(app.actions.addSlide({ collection }))
      setTimeout(() => swiper.slideTo(swiper.slides.length, 500), 150)
    }}>{t('SHOP_COLLECTIONS.ADD_SHOP')}</Button>
  )
}

const SaveButton = ({ collection }) => {

  const { t } = useTranslation();
  const [ updateShopCollection, { isLoading } ] = useUpdateShopCollectionMutation();

  return (
    <Button
      type="primary"
      loading={isLoading}
      onClick={() => {
        // TODO Validate all shops are set
        // TODO Check errors
        updateShopCollection(collection)
      }}
    >{t('SAVE_BUTTON')}</Button>
  )
}

const DeleteButton = ({ collection }) => {

  const { t } = useTranslation();
  const [ deleteShopCollection, { isLoading } ] = useDeleteShopCollectionMutation();

  const dispatch = useDispatch()
  const swiper = useSwiper();

  return (
    <Button danger onClick={async () => {
      const result = await deleteShopCollection(collection['@id']);
      console.log('result', result)
      dispatch(app.actions.removeCollection(collection))
    }}>{t('ADMIN_DASHBOARD_DELETE')}</Button>
  )
}

const CreateButton = () => {

  const { t } = useTranslation();
  const dispatch = useDispatch()
  const [ createShopCollection, { isLoading } ] = useCreateShopCollectionMutation();

  return (
    <Button
      type="primary"
      loading={isLoading}
      onClick={async () => {
        const collection = await createShopCollection({ title: 'Lorem ipsum' }).unwrap()
        dispatch(app.actions.addCollection(collection))
      }}
    >{t('SHOP_COLLECTIONS.ADD_COLLECTION')}</Button>
  )
}

const Editor = () => {

  const collections = useSelector((state) => state.app.collections)

  return (
    <div>
      {collections.map((collection, index) => (
        <div key={`collection-${index}`}>
          <CollectionSwiper collection={ collection } />
          <hr />
        </div>
      ))}
      <div>
        <Flex justify="flex-end" gap="small">
          <CreateButton />
        </Flex>
      </div>
    </div>
  )
}

const shopCollectionEditorEl = document.getElementById('shop-collection-editor');

if (shopCollectionEditorEl) {

  const shops = JSON.parse(shopCollectionEditorEl.dataset.shops)
  const collections = JSON.parse(shopCollectionEditorEl.dataset.collections)

  const store = configureStore({
    reducer: {
      app: app.reducer,
      [accountSlice.name]: accountSlice.reducer,
      [apiSlice.reducerPath]: apiSlice.reducer,
    },
    preloadedState: {
      app: {
        shops,
        collections,
      },
    },
    middleware: (getDefaultMiddleware) =>
      getDefaultMiddleware().concat([apiSlice.middleware]),
  })

  createRoot(shopCollectionEditorEl).render(
    <Provider store={store}>
      <Editor />
    </Provider>
  )
}
