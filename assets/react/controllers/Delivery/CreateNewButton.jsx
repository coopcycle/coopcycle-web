import React from 'react'
import { RootWithDefaults } from '../../../../js/app/utils/react'
import DeliveryCreateNewButton from '../../../../js/app/components/DeliveryCreateNewButton'

export default function CreateNewButton({ stores, routes }) {
  if (!stores || !routes) {
    return null
  }

  return (
    <RootWithDefaults>
      <DeliveryCreateNewButton stores={stores} routes={routes} />
    </RootWithDefaults>
  )
}
