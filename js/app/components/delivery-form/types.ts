import type { TimeSlotChoice, Tag, Package, Uri, TaskPayload } from '../../api/types'

export type Address = {
  '@id'?: string
  streetAddress: string
  name: string
  contactName: string
  telephone: string | null
  formattedTelephone?: string | null // Form-specific field for display
  geo?: {
    latitude: number
    longitude: number
  }
  description?: string
}

export type InputPackage = {
  '@id'?: string
  name: string
  type: string
  quantity: number
}

export type TimeSlot = {
  '@id': string
  name: string
  interval: string
  choices?: TimeSlotChoice[]
}

export type Task = {
  '@id': string | null
  type: 'PICKUP' | 'DROPOFF'
  after: string
  before: string
  timeSlot: TimeSlot | null
  timeSlotUrl: string | null
  comments: string
  address: Address
  updateInStoreAddresses?: boolean
  saveInStoreAddresses?: boolean
  packages: Package[]
  weight: number
  tags: Tag[]
  doorstep?: boolean
}

export type ManualSupplementValues = {
  '@id': Uri
  quantity: number
}

export type OrderFormValues = {
  isSavedOrder?: boolean
  manualSupplements: ManualSupplementValues[]
}

export type DeliveryFormValues = {
  tasks: Task[]
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
