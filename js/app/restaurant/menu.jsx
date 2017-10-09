import React from 'react';
import { render } from 'react-dom';
import { Switch } from 'antd';

function collapseAll() {
  $('#menu_sections .panel-collapse').collapse('hide');
}

function addForm($container) {

  var prototype = $container.data('prototype');
  var index = $container.children().length;

  var form = prototype.replace(/__name__/g, index);
  var $form = $(form);

  $container.append($form);

  if (!$container.closest('.collapse').hasClass('in')) {
    collapseAll();
    $container.closest('.collapse').collapse('show');
  }
}

function enableForm($form, enable) {
  if (enable) {
    $('#menu_addSection').removeAttr('disabled');
    $('#add-menu-section').removeAttr('disabled');
    $form.find('[type="submit"]').removeAttr('disabled');
  } else {
    $('#menu_addSection').attr('disabled', true);
    $('#add-menu-section').attr('disabled', true);
    $form.find('[type="submit"]').attr('disabled', true);
  }
}

function autoDismissMessages() {
  setTimeout(function() {
    $('#messages .alert').fadeOut();
  }, 1000);
}

$(function() {

  var $form = $('form[name="menu"]');

  autoDismissMessages();

  $(document).on('click', '.list-group-item .close', function(e) {
    e.preventDefault();
    $(e.target).closest('.list-group-item').remove();
  });

  $(document).on('click', '[data-toggle="add-menu-item"]', function(e) {
    e.preventDefault();
    var selector = $(e.target).data('target');
    var $target = $(selector);
    addForm($target);
  });

  $(document).on('click', '[data-toggle="remove-menu-section"]', function(e) {
    e.preventDefault();

    var selector = $(e.target).data('target');
    var $target = $(selector);

    var $copy = $target.detach();

    var data = $form.serialize();
    enableForm($form, false);

    $.ajax({
      url : $form.attr('action'),
      type: $form.attr('method'),
      data : data
    })
    .then(function(html) {
      enableForm($form, true);
      $copy.remove();
      $('#messages').replaceWith(
        $(html).find('#messages')
      );
      $('#menu_sections').replaceWith(
        $(html).find('#menu_sections')
      );
      $('#add-section-wrapper').replaceWith(
        $(html).find('#add-section-wrapper')
      );
      autoDismissMessages();
    })
    .catch(function(e) {
      enableForm($form, true);
    });
  });

  $('#menu_suggestions a').on('click', function(e) {
    e.preventDefault();
    $('#menu_addSection').val($(this).text());
  });

  $(document).on('show.bs.collapse', '[role="tabpanel"] .list-group-item .collapse', function () {
    $(this).closest('.list-group-item').find('.show-description').remove();
  });

  const regex = /^menu\[sections\]\[[0-9]+\]\[items\]\[[0-9]+\]\[isAvailable\]$/

  $('#menu_sections > .panel .list-group-item input[type="checkbox"]').each((index, el) => {

    if ($(el).attr('name').match(regex)) {

      const $parent = $(el).closest('div.checkbox').parent();

      const $switch = $('<div>');
      const $hidden = $('<input>')

      $switch.addClass('switch');

      $hidden
        .attr('type', 'hidden')
        .attr('name', $(el).attr('name'))
        .attr('value', $(el).attr('value'));

      $parent.append($switch);
      $parent.append($hidden);

      const checked = $(el).is(':checked');

      $(el).closest('div.checkbox').remove();

      render(
        <Switch defaultChecked={ checked }
          checkedChildren={ window.__i18n['Available'] } unCheckedChildren={ window.__i18n['Unavailable'] }
          onChange={(checked) => {
            if (checked) {
              $parent.append($hidden);
            } else {
              $hidden.remove();
            }
          }} />,
        $switch.get(0)
      );
    }

  })

  $(document).on('click', '#add-menu-section', function(e) {
    e.preventDefault();

    var data = $form.serialize();
    enableForm($form, false);

    $.ajax({
      url : window.__addSectionURL,
      type: $form.attr('method'),
      data : data
    })
    .then(function(html) {

      enableForm($form, true);

      $('#messages').replaceWith(
        $(html).find('#messages')
      );
      $('#menu_sections').replaceWith(
        $(html).find('#menu_sections')
      );
      autoDismissMessages();

      var sectionAdded = $('#menu_sections').data('section-added');
      if (sectionAdded) {
        var $el = $('[data-section-id="' + sectionAdded + '"]');
        addForm($('#' + $el.attr('id') + '_items'));
        $('#menu_sections').removeAttr('data-section-added');
        $('#menu_addSection').val('');
      }
    })
    .catch(function(e) {
      enableForm($form, true);
    });
  })

});
