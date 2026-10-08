import { ManualSupplementValues, TaskPayload, Uri } from '../../api/types';

export type OrderFormValues = {
  manualSupplements: ManualSupplementValues[];
  paymentMethod?: string;
  recalculatePrice?: boolean;
  isSavedOrder?: boolean;
  // A rule set chosen by a dispatcher, null to use the store's one
  pricingRuleSet?: Uri | null;
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

export type UploadContextType = {
  endpoint: string;
};
