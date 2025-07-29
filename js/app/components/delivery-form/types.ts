import type { TaskPayload } from '../../api/types'

export type OrderFormValues = {
  isSavedOrder?: boolean
}

export type DeliveryFormValues = {
  tasks: TaskPayload[]
  rrule?: string
  order?: OrderFormValues
  variantIncVATPrice?: number
  variantName?: string
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
