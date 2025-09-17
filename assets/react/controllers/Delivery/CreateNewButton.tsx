import { RootWithDefaults } from '../../../../js/app/utils/react'
import DeliveryCreateNewButton from '../../../../js/app/components/DeliveryCreateNewButton'

type Props = {
  stores: {
    id: string
    name: string
  }[]
  routes: {
    store_new: string
  }
}

export default function CreateNewButton({ stores, routes }: Props) {
  if (!stores || !routes) {
    return null
  }

  return (
    <RootWithDefaults>
      <DeliveryCreateNewButton stores={stores} routes={routes} />
    </RootWithDefaults>
  )
}
