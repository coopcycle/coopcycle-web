import React, { useMemo } from 'react'
import { useTranslation } from 'react-i18next'

const DELIVERY_TYPES = [
  { name: 'distance' },
  { name: 'pickup.address' },
  { name: 'dropoff.address' },
  { name: 'diff_hours(pickup)' },
  { name: 'diff_days(pickup)' },
  { name: "time_range_length(pickup, 'hours')" },
  { name: "time_range_length(dropoff, 'hours')" },
  { name: 'weight' },
  { name: 'packages' },
  { name: 'packages.totalVolumeUnits()' },
  { name: 'order.itemsTotal' },
  { name: 'vehicle', deprecated: true },
  { name: 'dropoff.doorstep', deprecated: true },
  { name: 'task.type', deprecated: true },
]

const TASK_TYPES = [
  { name: 'task.type' },
  { name: 'pickup.address' },
  { name: 'dropoff.address' },
  { name: "time_range_length(pickup, 'hours')" },
  { name: "time_range_length(dropoff, 'hours')" },
  { name: 'weight' },
  { name: 'packages' },
  { name: 'packages.totalVolumeUnits()' },
  { name: 'time_slot' },
  { name: 'diff_hours(pickup)', deprecated: true },
  { name: 'diff_days(pickup)', deprecated: true },
  { name: 'vehicle', deprecated: true },
  { name: 'dropoff.doorstep', deprecated: true },
  { name: 'distance', deprecated: true },
  { name: 'order.itemsTotal', deprecated: true },
]

const LEGACY_TARGET_DYNAMIC_TYPES = [
  { name: 'distance' },
  { name: 'pickup.address' },
  { name: 'dropoff.address' },
  { name: 'diff_hours(pickup)' },
  { name: 'diff_days(pickup)' },
  { name: "time_range_length(pickup, 'hours')" },
  { name: "time_range_length(dropoff, 'hours')" },
  { name: 'weight' },
  { name: 'packages' },
  { name: 'packages.totalVolumeUnits()' },
  { name: 'order.itemsTotal' },
  { name: 'vehicle' },
  { name: 'dropoff.doorstep' },
  { name: 'task.type' },
]

type RulePickerTypeProps = {
  ruleTarget: string
  type: { name: string; deprecated?: boolean }
}

function RulePickerType({ ruleTarget, type }: RulePickerTypeProps) {
  const { t } = useTranslation()

  const label = useMemo(() => {
    switch (type.name) {
      case 'pickup.address':
        return t('RULE_PICKER_LINE_PICKUP_ADDRESS')
      case 'dropoff.address':
        return t('RULE_PICKER_LINE_DROPOFF_ADDRESS')
      case 'diff_hours(pickup)':
        return t('RULE_PICKER_LINE_PICKUP_DIFF_HOURS')
      case 'diff_days(pickup)':
        return t('RULE_PICKER_LINE_PICKUP_DIFF_DAYS')
      case "time_range_length(pickup, 'hours')":
        return t('RULE_PICKER_LINE_PICKUP_TIME_RANGE_LENGTH_HOURS')
      case "time_range_length(dropoff, 'hours')":
        return t('RULE_PICKER_LINE_DROPOFF_TIME_RANGE_LENGTH_HOURS')
      case 'weight':
        switch (ruleTarget) {
          case 'DELIVERY':
            return t('RULE_PICKER_LINE_WEIGHT_TARGET_DELIVERY')
          case 'TASK':
            return t('RULE_PICKER_LINE_WEIGHT_TARGET_TASK')
          case 'LEGACY_TARGET_DYNAMIC':
            return t('RULE_PICKER_LINE_WEIGHT')
          default:
            return t('RULE_PICKER_LINE_WEIGHT')
        }
      case 'packages': {
        switch (ruleTarget) {
          case 'DELIVERY':
            return t('RULE_PICKER_LINE_PACKAGES_TARGET_DELIVERY')
          case 'TASK':
            return t('RULE_PICKER_LINE_PACKAGES_TARGET_TASK')
          case 'LEGACY_TARGET_DYNAMIC':
            return t('RULE_PICKER_LINE_PACKAGES')
          default:
            return t('RULE_PICKER_LINE_PACKAGES')
        }
      }
      case 'packages.totalVolumeUnits()':
        switch (ruleTarget) {
          case 'DELIVERY':
            return t('RULE_PICKER_LINE_VOLUME_UNITS_TARGET_DELIVERY')
          case 'TASK':
            return t('RULE_PICKER_LINE_VOLUME_UNITS_TARGET_TASK')
          case 'LEGACY_TARGET_DYNAMIC':
            return t('RULE_PICKER_LINE_VOLUME_UNITS')
          default:
            return t('RULE_PICKER_LINE_VOLUME_UNITS')
        }
      case 'task.type':
        return t('RULE_PICKER_LINE_TASK_TYPE')
      case 'distance':
        return t('RULE_PICKER_LINE_DISTANCE')
      case 'order.itemsTotal':
        return t('RULE_PICKER_LINE_ORDER_ITEMS_TOTAL')
      case 'vehicle':
        return t('RULE_PICKER_LINE_BIKE_TYPE')
      case 'dropoff.doorstep':
        return t('RULE_PICKER_LINE_DROPOFF_DOORSTEP')
      case 'time_slot':
        return t('RULE_PICKER_LINE_TIME_SLOT')
      default:
        return type.name
    }
  }, [ruleTarget, type, t])

  return (
    <option value={type.name}>
      {label}
      {type.deprecated && ` (${t('RULE_PICKER_LINE_OPTGROUP_DEPRECATED')})`}
    </option>
  )
}

type Props = {
  ruleTarget: string
  type: string
  onTypeSelect: (event: React.ChangeEvent<HTMLSelectElement>) => void
}

export function RulePickerTypeSelect({
  ruleTarget,
  type,
  onTypeSelect,
}: Props) {
  const { t } = useTranslation()

  const types = useMemo(() => {
    switch (ruleTarget) {
      case 'DELIVERY':
        return DELIVERY_TYPES
      case 'TASK':
        return TASK_TYPES
      case 'LEGACY_TARGET_DYNAMIC':
        return LEGACY_TARGET_DYNAMIC_TYPES
      default:
        return []
    }
  }, [ruleTarget])

  const nonDeprecatedTypes = useMemo(() => {
    return types.filter(type => !type.deprecated)
  }, [types])

  const deprecatedTypes = useMemo(() => {
    return types.filter(type => type.deprecated)
  }, [types])

  return (
    <select
      data-testid="condition-type-select"
      value={type}
      onChange={onTypeSelect}
      className="form-control input-sm">
      <option value="">-</option>
      {nonDeprecatedTypes.map((type, index) => (
        <RulePickerType
          ruleTarget={ruleTarget}
          type={type}
          key={`nonDeprecatedTypes-${index}`}
        />
      ))}
      {deprecatedTypes.length > 0 && (
        <optgroup label={t('RULE_PICKER_LINE_OPTGROUP_DEPRECATED')}>
          {deprecatedTypes.map((type, index) => (
            <RulePickerType
              ruleTarget={ruleTarget}
              type={type}
              key={`deprecatedTypes-${index}`}
            />
          ))}
        </optgroup>
      )}
    </select>
  )
}
