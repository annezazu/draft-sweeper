(function ($) {
  'use strict';

  $(document).on('click', '#draft_sweeper_widget .ds-dismiss', function (e) {
    e.preventDefault();
    var $btn = $(this);
    var $widget = $('#draft_sweeper_widget');
    var $hero = $btn.closest('.ds-hero');
    var postId = $hero.data('id');

    $btn.prop('disabled', true);
    $hero.addClass('is-dismissing');

    $.post(DraftSweeper.ajaxUrl, {
      action: 'draft_sweeper_dismiss',
      nonce: DraftSweeper.nonce,
      post_id: postId,
    }).done(function (resp) {
      if (resp && resp.success && resp.data && resp.data.html) {
        $widget.find('.inside').html(resp.data.html);
      } else {
        $hero.removeClass('is-dismissing');
        $btn.prop('disabled', false);
      }
    }).fail(function () {
      $hero.removeClass('is-dismissing');
      $btn.prop('disabled', false);
    });
  });
})(jQuery);
