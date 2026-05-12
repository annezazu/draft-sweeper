(function ($) {
  'use strict';

  function refresh($widget) {
    return $.post(DraftSweeper.ajaxUrl, {
      action: 'draft_sweeper_refresh',
      nonce: DraftSweeper.nonce,
    }).done(function (resp) {
      if (resp && resp.success) {
        $widget.find('.inside').html(resp.data.html);
      }
    });
  }

  $(document).on('click', '#draft_sweeper_widget .ds-ai-toggle__form-toggle, #draft_sweeper_widget .ds-ai-toggle__label', function (e) {
    e.preventDefault();
    var $btn = $(this).hasClass('ds-ai-toggle__form-toggle')
      ? $(this)
      : $(this).siblings('.ds-ai-toggle__form-toggle');
    if (! $btn.length) return;

    var next = $btn.attr('aria-checked') !== 'true';
    var $wrap = $btn.closest('.ds-ai-toggle');
    var $tooltip = $wrap.find('.ds-ai-toggle__tooltip');
    var $body = $btn.closest('.ds-widget').find('.ds-body-wrap');

    $btn.toggleClass('is-checked', next).attr('aria-checked', next ? 'true' : 'false').addClass('is-saving');
    if ($tooltip.length) {
      $tooltip.text(next ? $tooltip.data('on') : $tooltip.data('off'));
    }

    $.post(DraftSweeper.ajaxUrl, {
      action: 'draft_sweeper_toggle_ai',
      nonce: DraftSweeper.nonce,
      enabled: next ? '1' : '0',
    }).done(function (resp) {
      $btn.removeClass('is-saving');
      if (resp && resp.success && resp.data && typeof resp.data.html === 'string') {
        $body.html(resp.data.html);
        return;
      }
      $btn.toggleClass('is-checked', !next).attr('aria-checked', next ? 'false' : 'true');
      if ($tooltip.length) {
        $tooltip.text(next ? $tooltip.data('off') : $tooltip.data('on'));
      }
    }).fail(function () {
      $btn.removeClass('is-saving').toggleClass('is-checked', !next).attr('aria-checked', next ? 'false' : 'true');
      if ($tooltip.length) {
        $tooltip.text(next ? $tooltip.data('off') : $tooltip.data('on'));
      }
    });
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
