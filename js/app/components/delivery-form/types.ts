import type { Uri, TaskPayload } from '../../api/types'

export type ManualSupplementValues = {
  '@id': Uri
  quantity: number
}

export type OrderFormValues = {
  isSavedOrder?: boolean
  manualSupplements: ManualSupplementValues[]
}

export type DeliveryFormValues = {
  tasks: TaskPayload[]
  rrule?: string
  order: OrderFormValues
  variantIncVATPrice?: number
  variantName?: string
}

export type TaskErrors = {
  address?: {
    streetAddress?: string
    name?: string
    contactName?: string
    formattedTelephone?: string
  }
  packages?: string
  weight?: string
  after?: string
  before?: string
}

export type FormErrors = {
  tasks: TaskErrors[]
}

export type FlagsContextType = {
  isDispatcher: boolean
  isDebugPricing: boolean
  isPriceBreakdownEnabled: boolean
}

export type PriceValues = {
  VAT: number | null
  exVAT: number | null
}
