// Common TypeScript interfaces for delivery form components

export interface Address {
  '@id'?: string
  streetAddress: string
  name: string
  contactName: string
  telephone: string | null
  formattedTelephone?: string | null
  geo?: {
    latitude: number
    longitude: number
  }
  description?: string
}

export interface Package {
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

export interface TimeSlotChoice {
  '@id': string
  startTime: string
  endTime: string
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

export interface Tag {
  '@id': string
  name: string
  slug: string
  color: string
}

export interface Store {
  '@id': string
  name: string
  timeSlots: string[]
  timeSlot: string | null
  packages?: Package[]
  deliveryPerimeterExpression?: string
  packagesRequired?: boolean
  weightRequired?: boolean
}

export interface ProductOptionValue {
  '@id': string
  value: string
  price: number
}

export interface ProductVariant {
  '@id': string
  name: string
  optionValues: ProductOptionValue[]
}

export interface OrderItem {
  '@id': string
  variant: ProductVariant
  quantity: number
  total: number
}

export interface Order {
  '@id': string
  items: OrderItem[]
  itemsTotal: number
  total: number
  adjustmentsTotal: number
}

export interface Delivery {
  '@id': string
  tasks: Task[]
  order?: Order
  distance?: number
  duration?: number
  polyline?: string
}

export interface DeliveryFormValues {
  tasks: Task[]
  rrule?: string
  order?: Order
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

export interface StoreDeliveryInfos {
  packagesRequired: boolean
  weightRequired: boolean
  timeSlotRequired?: boolean
}

export interface FlagsContextType {
  isDispatcher: boolean
  isDebugPricing: boolean
  isPriceBreakdownEnabled: boolean
}

export interface RecurrenceRule {
  '@id': string
  rule: string
  template: Delivery
}
