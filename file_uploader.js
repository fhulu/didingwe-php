(function($) {
  $.widget("ui.file_uploader", {
    options: {
      uploader: ''
    },

    _create: function()
    {
      var el = this.element
      var form = el.find('form')
        .attr('action',this.options.uploader+'&path='+encodeURIComponent(this.options.path));
      form.att
      var upload = el.find('#upload').click(function() {
        form.submit();
      });
      var progress = el.find('#progress');
      var file = el.find("#file").change(function() {
        uploaded.hide();
        failed.hide();
        upload.show();
        progress.hide();
      });
      var bar = el.find('#bar');
      var percent = el.find("#percent");
      var cancel = el.find('#cancel');
      var uploaded = el.find('#uploaded');
      var failed = el.find('#failed');
      form.ajaxForm({
        beforeSend: function(xhr)
        {
          bar.width('0%');
          percent.html('0%').zIndex(bar.zIndex()+1);;
          progress.width('100%').fadeIn();
          $('.error').remove();
          upload.hide();
          uploaded.hide();
          failed.hide();
          cancel.click(xhr.abort).show();
        },

        uploadProgress: function(event, position, total, done)
        {
          bar.width(done + '%');
          percent.html('Uploaded ' + position + ' of ' + total +'(' + done +'%)');
        },

        success: function(result, textStatus, xhr)
        {
          result = $.parseJSON(result);
          el.trigger('server_response', [result]);
          if (result && result._responses && result._responses.errors)
            return this.error();
            cancel.hide();
          percent.text('Uploaded');
          progress.animate({width:0}, 200, function() {
            uploaded.show();
          });
        },

        error: function()
        {
          cancel.hide();
          progress.animate({width:0}, 200, function() {
            failed.show();
          });
          return this;
        }
      });
    }
  })
})(jQuery);
