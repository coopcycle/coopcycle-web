import { ManualSupplementValues, TaskPayload } from '../../api/types';

export type OrderFormValues = {
  manualSupplements: ManualSupplementValues[];
  paymentMethod?: string;
  recalculatePrice?: boolean;
  isSavedOrder?: boolean;
};

export type DeliveryFormValues = {
  tasks: TaskPayload[];
  rrule?: string;
  order: OrderFormValues;
  variantIncVATPrice?: number;
  variantName?: string;
  addReverse?: boolean;
};

export type FlagsContextType = {
  isDebugPricing: boolean;
  isPriceBreakdownEnabled: boolean;
  isReverseDeliveryEnabled: boolean;
};

export type PriceValues = {
  VAT: number;
  exVAT: number;
};
