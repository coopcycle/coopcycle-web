import type { TimeSlotChoice, Tag, Package } from '../../api/types'

export interface Address {
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

export interface InputPackage {
  '@id'?: string
  name: string
  type: string
  quantity: number
}

export interface TimeSlot {
  '@id': string
  name: string
  interval: string
  choices?: TimeSlotChoice[]
}

export interface Task {
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

export interface OrderFormValues {
  isSavedOrder?: boolean
}

export interface DeliveryFormValues {
  tasks: Task[]
  rrule?: string
  order?: OrderFormValues
  variantIncVATPrice?: number
  variantName?: string
}

export interface TaskErrors {
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

export interface FormErrors {
  tasks: TaskErrors[]
}

export interface FlagsContextType {
  isDispatcher: boolean
  isDebugPricing: boolean
  isPriceBreakdownEnabled: boolean
}

export type PriceValues = {
  VAT: number | null
  exVAT: number | null
}
