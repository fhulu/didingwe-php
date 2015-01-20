(function($) {
  $.widget("ui.file_uploader", {
    options: {
      uploader: ''
    },
    
    _create: function()
    {
      var el = this.element.addClass('file_uploader');
      var id = el.attr('id');
      var form = $('<form method=POST></form>')
              .attr('action',this.options.uploader)
              .attr('target',id+'_target')
              .appendTo(el);
      var file = $('<input type=file></input>').attr('name',id+'_file').appendTo(form);
      $('<input type=hidden></input>').attr('name',id+'_id').appendTo(el);
      var button = $('<button>Upload</button>').appendTo(el).hide().click(function() {
        form.submit();
      });
      var progress = $('<div class=progress></div>').hide().appendTo(el);
      var bar = $('<div class=bar></div>').appendTo(progress);
      var percent = $('<div class=percent></div>').appendTo(progress);
      $('<iframe src="#"></iframe>').attr('target',id+'_target').appendTo(el);
      file.change(function() {
         button.removeAttr('disabled').text('Upload').show();
         progress.hide();
      });
      form.ajaxForm({
        beforeSend: function()
        {
          bar.width('0%');
          percent.html('0%');
          progress.show();
          button.attr('disabled','disabaled').text('Uploading...');
        },

        uploadProgress: function(event, position, total, done) 
        {
          var val = done + '%';
          bar.width(val);
          percent.html('Uploaded ' + position + ' of ' + total +'(' + val +')');
        },

        success: function(response, textStatus, xhr) 
        {
//          if (response.length && response != "ok" ) {
//            alert(response);
//            return xhr.abort();
//          }
          bar.width('100%')
          percent.html('Finished uploading');
          button.hide();
          el.trigger('uploaded');
        },

        error: function(x) 
        {
           alert("Error uploading your document(s). Please try again.");
         }
      });
    }
  })
})(jQuery);

