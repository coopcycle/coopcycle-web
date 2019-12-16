import MapHelper from '../MapHelper'
import L from 'leaflet'
import _ from 'lodash'

if ($('form[name="zone_collection"]').length === 1) {
  $('#zone_collection_zones').find('input[type="hidden"]')
    .each(function() {

      const $hiddenInput = $(this)
      const $thumbnail = $(this).closest('.thumbnail')

      const feature = JSON.parse($hiddenInput.val())

      const map = MapHelper.init($hiddenInput.attr('id') + '-map')
      const layer = L.geoJSON(feature).addTo(map)
      MapHelper.fitToLayers(map, [ layer ])

      const $checkbox = $('#' + $hiddenInput.attr('id') + '-checkbox')
      $checkbox.on('change', function() {
        if ($(this).is(':checked')) {
          $thumbnail.css('opacity', 1)
          $thumbnail.find('input').not('input[type="checkbox"]').removeAttr('disabled')
        } else {
          $thumbnail.css('opacity', 0.4)
          $thumbnail.find('input').not('input[type="checkbox"]').attr('disabled', true)
        }
      })

    })
}

_.each(window.AppData.Zones, zone => {
  const $el = $('[data-zone="' + zone.id + '"]')
  if ($el.length === 1) {
    const map = MapHelper.init('zone-' + zone.id)
    const layer = L.geoJSON(zone.geoJSON).addTo(map)
    MapHelper.fitToLayers(map, [ layer ])
  }
})
