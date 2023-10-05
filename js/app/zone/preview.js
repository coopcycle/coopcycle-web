import MapHelper from '../MapHelper'
import L from 'leaflet'

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

document.querySelectorAll('[data-zone]').forEach(el => {
  const map = MapHelper.init(el.getAttribute('id'))
  const layer = L.geoJSON(JSON.parse(el.dataset.zone)).addTo(map)
  MapHelper.fitToLayers(map, [ layer ])
})

document.querySelectorAll('[data-download]').forEach(el => {
  const geojson = {
    "type": "FeatureCollection",
    "features": [
      {
        "type": "Feature",
        "properties": {},
        "geometry": JSON.parse(el.dataset.download)
      }]
  };
  const blob = new Blob([ JSON.stringify(geojson) ], { type: 'application/octet-stream' })
  el.href = window.URL.createObjectURL(blob)
  el.download = 'geojson-' + el.dataset.name + '.json'
})
