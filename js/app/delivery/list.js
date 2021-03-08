/* */

new CoopCycle.DatePicker(document.querySelector('#data_export_start_widget'), {
  onChange: function(date) {
    if (date) {
      document.querySelector('#data_export_start').value = date.format('YYYY-MM-DD');
    }
  }
});

new CoopCycle.DatePicker(document.querySelector('#data_export_end_widget'), {
  onChange: function(date) {
    if (date) {
      document.querySelector('#data_export_end').value = date.format('YYYY-MM-DD');
    }
  }
});
