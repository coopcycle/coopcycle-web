import Spreadsheet from 'x-data-spreadsheet'
import 'x-data-spreadsheet/dist/xspreadsheet.css'

const opts = {
  showToolbar: false,
  showGrid: true,
  showContextmenu: false,
  view: {
    height: () => 30 * 5,
  },
  row: {
    len: 5,
  },
  col: {
    len: 4,
  },
}

let s

$('#export-deliveries-modal').on('show.bs.modal', function () {

  if (!s) {
    s = new Spreadsheet("#spreadsheet", opts)
      .loadData({
        cols: {
          "0":{"width":126},
          "1":{"width":140},
          "2":{"width":228},
          "3":{"width":228},
        },
        rows: {
          "0":{
            "cells":{
              "0":{"text":"pickup.address"},
              "1":{"text":"dropoff.address"},
              "2":{"text":"pickup.timeslot"},
              "3":{"text":"dropoff.timeslot"},
            }
          },
          "1":{
            "cells":{
              "0":{"text":"24 rue de rivoli paris"},
              "1":{"text":"58 av parmentier paris"},
              "2":{"text":"2019-12-12 10:00 – 2019-12-12 11:00"},
              "3":{"text":"2019-12-12 12:00 – 2019-12-12 13:00"},
            }
          },
          "2":{
            "cells":{
              "0":{"text":"24 rue de rivoli paris"},
              "1":{"text":"34 bd de magenta paris"},
              "2":{"text":"2019-12-12 10:00 – 2019-12-12 11:00"},
              "3":{"text":"2019-12-12 12:00 – 2019-12-12 13:00"},
            }
          }
        }
      })
  }

})
