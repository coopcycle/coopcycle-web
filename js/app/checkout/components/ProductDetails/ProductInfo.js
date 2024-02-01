import React from 'react'
import { useTranslation } from 'react-i18next'
import ProductBadge from './ProductBadge'

export default function ProductInfo ({ product }) {
  const { t } = useTranslation()

  const badges = []

  if (product.allergens) {
    product.allergens.forEach(item => {
      badges.push({
        type: 'allergen',
        value: t(`ALLERGEN.${item}`),
      })
    })
  }

  if (product.suitableForDiet) {
    product.suitableForDiet.forEach(item => {
      badges.push({
        type: 'restricted_diet',
        value: t(`RESTRICTED_DIET.${item.replace('http://schema.org/', '')}`),
      })
    })
  }

  return (
    <div>
      {
        product.description ? (
          <div>{product.description}</div>
        ) : null
      }
      {
        badges.length > 0 ? (
          <div className="product-badge-container mt-3">
            {
              badges.map((badge, index) => (
                <ProductBadge key={index} type={badge.type}
                              value={badge.value}/>
              ))
            }
          </div>
        ) : null
      }
    </div>
  )
}
