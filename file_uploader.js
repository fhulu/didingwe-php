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
      $('<input type=hidden name=path></input>').val(this.options.path).appendTo(form);
      $('<input type=hidden></input>').attr('name',id+'_id').appendTo(el);
      var button = $('<button>Upload</button>').appendTo(el).hide().click(function() {
        form.submit();
      });
      var progress = $('<div class=progress></div>').hide().appendTo(el);
      var bar = $('<div class=bar></div>').appendTo(progress);
      var percent = $('<span class=percent></span>').appendTo(progress);
      $('<iframe src="#"></iframe>').attr('target',id+'_target').appendTo(el);
      file.change(function() {
         button.show();
         progress.hide();
      });
      form.ajaxForm({
        beforeSend: function()
        {
          bar.width('0%');
          percent.html('0%');
          progress.removeClass("uploaded error").fadeIn();
          button.hide();
        },

        uploadProgress: function(event, position, total, done) 
        {
          bar.width(done + '%');
          percent.html('Uploaded ' + position + ' of ' + total +'(' + done +'%)');
        },

        success: function(response, textStatus, xhr) 
        {
          percent.text('Uploaded');
          progress.animate({width:'80px'}, 200).addClass('uploaded');
          el.trigger('uploaded');
        },

        error: function(x) 
        {
          percent.text('Error');
          progress.animate({width:'80px'}, 200).addClass('error');
        }
      });
    }
  })
})(jQuery);

