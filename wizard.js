(function( $ ) {
  $.widget( "ui.wizard", {
    _create: function() {
      this.stack = new Array();   
      this.dialogs = new Array();
      this.last_url = undefined;
      var self = this;
      $.each(this.element.children(), function(i) {
        var dialog = $(this);
        var div = dialog.dialog({
          modal: true,
          autoOpen: false,
          width: parseInt($(this).css('width')),
          //note: show commented out, using show causes an exception if div contains input[type=file]
//          show: 'blind', 
          hide: 'explode'
        });
        self.dialogs.push(div);
        
        $(this).find("[wizard='next']").click(function() {
          var steps = $(this).attr('steps');
          self.goNext(steps);
        });

        $(this).find("[wizard='back']").click(function() {
          self.goBack();
        });
        
        $(this).find("[wizard='close']").click(function() {
          if (self.last_url != undefined)
            window.location.href = self.last_url;
          dialog.dialog('close');
        });
        
      });

    },
    
    onNext: function(index, callback)
    {
      var dialog = this.element.children().eq(index);
      dialog.find("[wizard='next']").click(callback);
    },
    
    dialog: function(selector) { 
      return $(this).find(selector); 
    },
    
    start: function(index) {
      if (index == undefined) index = 0;
      this.stack = new Array();
      for (var i=0; i < index; i++)
        this.stack.push(1);
      this.dialogs[index].dialog('open');
      return false;
    },
    
    goNext: function(steps) {
      steps = steps==undefined? 1: parseInt(steps);
      var cur_idx = 0;
      var self = this;
      $.each(self.stack, function(i, v) {
        cur_idx += parseInt(v);
      });
  
      $.each(self.dialogs, function(i) {
        var dialog = this.dialog().data('dialog');
        if (i == cur_idx) {
          if (dialog.isOpen()) { 
            self.auto_closed = true;
            dialog.close();
          }
          else {
            self.auto_closed = false;
            dialog.open();
            return false;
          }   
          // make sure we remember to go back the same number of times we went forward
          cur_idx += steps;
          self.stack.push(steps);
        }
      });
    },
    
    goBack: function() {
      var cur_idx = 0;
      $.each(this.stack, function(i, v) {
        cur_idx += v;
      });
         
      $.each(this.dialogs, function(i) {
        if (i == cur_idx) {
            self.auto_closed = true;
          this.dialog('close');
          return false;
        }
      });
   
      cur_idx -= this.stack.pop();
      $.each(this.dialogs, function(i) {
        if (i == cur_idx) {
            self.auto_closed = false;
          this.dialog('open');
          return false;
        }
      });
    },

    close: function() 
    {
      var cur_idx = 0;
      var self = this;
      $.each(self.stack, function(i, v) {
        cur_idx += parseInt(v);
      });
  
      this.dialogs[cur_idx].dialog('close');
    },
        
    bind: function(event, callback) 
    {
      $.each(this.dialogs, function() {
        $(this).bind( event, function(evt, ui) {
          callback(evt, ui);
        });
      });
    }
  })
}) (jQuery);
