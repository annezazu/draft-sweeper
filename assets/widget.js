(function ($) {
  'use strict';

  function refresh($widget, $button) {
    if ($button) { $button.addClass('is-loading').prop('disabled', true); }
    return $.post(DraftSweeper.ajaxUrl, {
      action: 'draft_sweeper_refresh',
      nonce: DraftSweeper.nonce,
    }).done(function (resp) {
      if (resp && resp.success) {
        $widget.find('.inside').html(resp.data.html);
      }
    }).always(function () {
      if ($button) { $button.removeClass('is-loading').prop('disabled', false); }
    });
  }

  $(document).on('click', '#draft_sweeper_widget .ds-refresh', function (e) {
    e.preventDefault();
    refresh($('#draft_sweeper_widget'), $(this));
  });

  $(document).on('click', '#draft_sweeper_widget .ds-dismiss', function (e) {
    e.preventDefault();
    var $item = $(this).closest('.ds-item');
    var postId = $item.data('id');
    var $widget = $('#draft_sweeper_widget');
    $item.addClass('is-dismissing');
    $.post(DraftSweeper.ajaxUrl, {
      action: 'draft_sweeper_dismiss',
      nonce: DraftSweeper.nonce,
      post_id: postId,
    }).done(function () {
      refresh($widget);
    }).fail(function () {
      $item.removeClass('is-dismissing');
    });
  });
})(jQuery);
