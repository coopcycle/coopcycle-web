import 'fullcalendar'
import 'fullcalendar/dist/locale-all'
import moment from 'moment'

const $calendar = $('#calendar')
const $modal = $('#event-modal')

$modal.on('show.bs.modal', function(e) {

})

$calendar.fullCalendar({
  defaultDate: moment(window.AppData.Schedules.currentDate),
  defaultView: 'agendaWeek',
  locale: 'fr',
  dayClick: (date, e, view, resource) => {
    console.log('dayClick', date.format('YYYY-MM-DD'))
    $calendar.fullCalendar('renderEvent', {
        title: 'Shift #1',
        editable: true,
        start: date.format(),
        end: date.add(2, 'hour').format(),
        allDay: false
    }, true)
  },
  eventClick: (event, e, view) => {
    console.log('eventClick', event)
    $modal.find('.modal-title').text(event.title)
    $modal.modal('show')
  },
  eventRender: function(event, element) {
    console.log(element)
  },
  events: [
    {
      title: 'Shift #1',
      editable: true,
      start: '2018-01-09T12:30:00',
      end: '2018-01-09T16:30:00',
      allDay: false
    },
    {
      title: 'Shift #2',
      editable: true,
      start: '2018-01-10T09:30:00',
      end: '2018-01-10T12:30:00',
      allDay: false
    }
  ]
})
