import React, { useState, useEffect, useRef } from 'react'
import { useSelector, useDispatch } from 'react-redux'
import { useTranslation } from 'react-i18next'
import { Swiper, SwiperSlide } from 'swiper/react'
import { Navigation } from 'swiper/modules'
import { ChevronLeft, ChevronRight } from 'lucide-react'
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

  const prevRef = useRef(null)
  const nextRef = useRef(null)

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
    window._paq.push(['trackEvent', 'Checkout', 'addRecommendedItem'])
    dispatch(queueAddItem(item.formAction, 1))
  }

  if (loading || items.length === 0) return null

  return (
    <div className="border-t border-base-300 pt-3 pb-1 px-3">
      <div className="flex items-center justify-between mb-3">
        <h5 className="font-semibold text-sm">{t('FREQUENTLY_BOUGHT_TOGETHER')}</h5>
        <div className="flex gap-1">
          <button ref={prevRef} type="button" aria-label={t('PREVIOUS')} className="fbt-nav-btn btn btn-circle btn-ghost btn-sm">
            <ChevronLeft size={16} />
          </button>
          <button ref={nextRef} type="button" aria-label={t('NEXT')} className="fbt-nav-btn btn btn-circle btn-ghost btn-sm">
            <ChevronRight size={16} />
          </button>
        </div>
      </div>
      <Swiper
        modules={[Navigation]}
        slidesPerView={2}
        spaceBetween={8}
        navigation={{
          prevEl: prevRef.current,
          nextEl: nextRef.current,
        }}
        onBeforeInit={swiper => {
          swiper.params.navigation.prevEl = prevRef.current
          swiper.params.navigation.nextEl = nextRef.current
        }}
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
