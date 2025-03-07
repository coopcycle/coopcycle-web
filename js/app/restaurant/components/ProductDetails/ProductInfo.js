import React from 'react'
import { useTranslation } from 'react-i18next'
import ProductBadge from './ProductBadge'

export default function ProductInfo ({ product }) {
  const { t } = useTranslation()

  const diets = []
  if (product.suitableForDiet) {
    product.suitableForDiet.forEach(item => {
      diets.push({
        type: 'restricted_diet',
        value: t(`RESTRICTED_DIET.${item.replace('http://schema.org/', '')}`),
      })
    })
  }

  const allergens = []
  if (product.allergens) {
    product.allergens.forEach(item => {
      allergens.push({
        type: 'allergen',
        value: t(`ALLERGEN.${item}`),
      })
    })
  }

  if (product.description || diets.length > 0 || allergens.length > 0) {
    return (
      <div className="product-info">
        {
          product.description ? (
            <div>{product.description}</div>
          ) : null
        }
        {
          diets.length > 0 ? (
            <div className="product-badge-container">
              {
                diets.map((badge, index) => (
                  <ProductBadge key={index} type={badge.type}
                                value={badge.value}/>
                ))
              }
            </div>
          ) : null
        }
        {
          allergens.length > 0 ? (
            <div className="product-badge-container">
              {
                allergens.map((badge, index) => (
                  <ProductBadge key={index} type={badge.type}
                                value={badge.value}/>
                ))
              }
            </div>
          ) : null
        }
      </div>
    )
  } else {
    return null
  }
}
