import React from 'react'
import { createRoot } from 'react-dom/client'
import { RootWithDefaults } from '../../../utils/react'
import Map from '../../../components/DeliveryMap'
import Itinerary from '../../../components/DeliveryItinerary'

const el = document.querySelector('#delivery-info')

if (el) {
  const delivery = JSON.parse(el.dataset.delivery)

  const root = createRoot(el)
  root.render(
    <RootWithDefaults>
      <div>
        <Map
          defaultAddress={delivery.tasks[0].address}
          tasks={delivery.tasks}
        />
        <div className="py-3" />
        <Itinerary
          tasks={delivery.tasks}
          withTimeRange
          withDescription
          withPackages
        />
      </div>
    </RootWithDefaults>,
  )
}
