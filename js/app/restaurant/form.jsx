import React from 'react'
import { render } from 'react-dom'
import Switch from 'antd/lib/switch'
import i18n from '../i18n'
import QueryBuilder from 'jQuery-QueryBuilder'
// import 'jQuery-QueryBuilder/dist/i18n/query-builder.fr.js'

function renderSwitch($input) {

  const $parent = $input.closest('div.checkbox').parent()

  const $switch = $('<div class="display-inline-block">')
  const $hidden = $('<input>')

  $switch.addClass('switch')

  $hidden
    .attr('type', 'hidden')
    .attr('name', $input.attr('name'))
    .attr('value', $input.attr('value'))

  $parent.prepend($switch)

  const checked = $input.is(':checked'),
        disabled = $input.is(':disabled')

  if (checked) {
    $parent.prepend($hidden)
  }

  $input.closest('div.checkbox').remove()

  render(
    <Switch defaultChecked={ checked }
            checkedChildren={ i18n.t('ENABLED') }
            unCheckedChildren={ i18n.t('DISABLED') }
            onChange={(checked) => {
              if (checked) {
                $parent.append($hidden)
              } else {
                $hidden.remove()
              }
            }}
            disabled={disabled}
        />,
    $switch.get(0)
  );

}

var rules_basic = {
  condition: 'AND',
  rules: [{
    id: 'price',
    operator: 'less',
    value: 10.25
  }, {
    condition: 'OR',
    rules: [{
      id: 'category',
      operator: 'equal',
      value: 2
    }, {
      id: 'category',
      operator: 'equal',
      value: 1
    }]
  }]
};

console.log(QueryBuilder)
console.log($('#query-builder'))

// new QueryBuilder($('#query-builder'), {
$('#query-builder').queryBuilder({
  plugins: ['bt-tooltip-errors'],
  lang_code: 'fr',
  allow_groups: false,

  filters: [{
    id: 'distance',
    label: 'Distance',
    type: 'integer',
    operators: ['less', 'less_or_equal', 'greater', 'greater_or_equal', 'between']
  }, {
    id: 'dropoff_address',
    label: 'Dropoff address',
    type: 'integer',
    input: 'select',
    values: {
      1: 'Books',
      2: 'Movies',
      3: 'Music',
      4: 'Tools',
      5: 'Goodies',
      6: 'Clothes'
    },
    operators: ['in_zone', 'out_zone']
  }],

  operators: [
    { type: 'less' },
    { type: 'less_or_equal' },
    { type: 'greater' },
    { type: 'greater_or_equal' },
    { type: 'between' },
    { type: 'in_zone', nb_inputs: 1, multiple: false, apply_to: ['number'] },
    { type: 'out_zone', nb_inputs: 1, multiple: false, apply_to: ['number'] }
  ]

  // rules: rules_basic
});

$('#get-rules').on('click', () => {
  var result = $('#query-builder').queryBuilder('getRules');

  if (!$.isEmptyObject(result)) {
    console.log(JSON.stringify(result, null, 2));
  }
})


$(function() {
  // Render Switch on page load
  $('form[name="restaurant"]').find('.switch').each((index, el) => renderSwitch($(el)))
  window.CoopCycle.DeliveryZonePicker(
    $('#restaurant_deliveryPerimeterExpression__picker').get(0),
    {
      zones: window.AppData.zones,
      expression: window.AppData.deliveryPerimeterExpression,
      onExprChange: (expr) => { $('#restaurant_deliveryPerimeterExpression').val(expr)}
    }
  )
})
