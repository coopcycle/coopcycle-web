import { PricingRule } from '../../../api/types'
import { Checkbox, CheckboxChangeEvent } from 'antd'

type Props = {
  rule: PricingRule
}

export default function ManualSupplement({ rule }: Props) {
  //TODO; display price (similarly to foodtech) (fixed; percentage)
  //TODO; add support for range type

  const onChange = (e: CheckboxChangeEvent) => {
    //TODO
    console.log('checked = ', e.target.checked)
  }

  return (
    <div>
      <Checkbox onChange={onChange}>{rule.name}</Checkbox>
    </div>
  )
}
