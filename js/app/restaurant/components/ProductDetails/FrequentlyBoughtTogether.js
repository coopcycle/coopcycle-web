import React, { useState, useEffect } from 'react'
import { useSelector, useDispatch } from 'react-redux'
import { useTranslation } from 'react-i18next'
import { Swiper, SwiperSlide } from 'swiper/react'
import { Navigation } from 'swiper/modules'
import 'swiper/css'
import 'swiper/css/navigation'

import { queueAddItem } from '../../redux/actions'

function FbtCard({ item, onAddToCart }) {
  const product = item.product
  const price   = product.offers?.price
  const image   = product.images?.[0]?.url

  return (
    <button
      type="button"
      className="w-full text-left card bg-base-200 cursor-pointer hover:bg-base-300 transition-colors"
      onClick={() => onAddToCart(item)}>
      <figure className="aspect-square overflow-hidden rounded-t-xl bg-base-300">
        {image && <img src={image} alt={product.name} className="w-full h-full object-cover" />}
      </figure>
      <div className="card-body gap-1 basis-32 shrink-0 overflow-hidden">
        <p className="text-sm font-medium line-clamp-3 leading-tight">{product.name}</p>
        {price !== undefined && (
          <p className="text-sm text-base-content/60 grow-0">{(price / 100).formatMoney()}</p>
        )}
      </div>
    </button>
  )
}

export default function FrequentlyBoughtTogether({ product }) {
  const { t } = useTranslation()
  const dispatch = useDispatch()
  const restaurant = useSelector(state => state.restaurant)

  const [items, setItems]     = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    if (!product?.['@id'] || !restaurant) {
      setLoading(false)
      return
    }

    const productId = product['@id'].split('/').pop()
    const params = new URLSearchParams({ n: 5 })
    if (restaurant['@id']) {
      params.set('restaurant', restaurant['@id'])
    }

    fetch(`/api/products/${productId}/recommendations?${params}`, {
      headers: { Accept: 'application/ld+json' },
    })
      .then(r => (r.ok ? r.json() : Promise.reject()))
      .then(data => setItems(data.items ?? []))
      .catch(() => {})
      .finally(() => setLoading(false))
  }, [product?.['@id']])

  const handleAddToCart = (item) => {
    dispatch(queueAddItem(item.formAction, 1))
  }

  if (loading || items.length === 0) return null

  return (
    <div className="border-t border-base-300 pt-3 pb-1 px-3">
      <h5 className="mb-3 font-semibold text-sm">{t('FREQUENTLY_BOUGHT_TOGETHER')}</h5>
      <Swiper
        modules={[Navigation]}
        slidesPerView={2}
        spaceBetween={8}
        navigation
        className="fbt-swiper">
        {items.map((item, i) => (
          <SwiperSlide key={item.product?.['@id'] ?? i}>
            <FbtCard item={item} onAddToCart={handleAddToCart} />
          </SwiperSlide>
        ))}
      </Swiper>
    </div>
  )
}
