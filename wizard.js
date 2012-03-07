(function( $ ) {
  $.widget( "ui.wizard", {
    _create: function() {
      this.stack = new Array();   
      this.dialogs = new Array();
      var self = this;
      $.each(this.element.children(), function(i) {
        var div = $(this).dialog({
          modal: true,
          autoOpen: false,
          width: parseInt($(this).css('width')),
          height: parseInt($(this).css('height')),
          show: 'blind',
          hide: 'explode',
        });
        self.dialogs.push(div);
        
        $(this).find("[wizard='next']").click(function() {
          var steps = this.getAttribute('steps');
          if (steps == undefined) steps = 1;
          self.go_next(steps);
        });

        $(this).find("[wizard='back']").click(function() {
          self.go_back();
        });
        
      });

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
    
    go_next: function(steps) {
      var cur_idx = 0;
      var self = this;
      $.each(self.stack, function(i, v) {
        cur_idx += parseInt(v);
      });
  
      $.each(self.dialogs, function(i) {
        var dialog = this.dialog().data('dialog');
        if (i == cur_idx) {
          if (dialog.isOpen()) { 
            dialog.close();
          }
          else {
            dialog.open();
            return false;
          }   
          // make sure we remember to go back the same number of times we went forward
          cur_idx += steps;
          self.stack.push(steps);
        }
      });
    },
    
    go_back: function() {
      var cur_idx = 0;
      $.each(this.stack, function(i, v) {
        cur_idx += v;
      });
         
      $.each(this.dialogs, function(i) {
        if (i == cur_idx) {
          this.dialog('close');
          return false;
        }
      });
   
      cur_idx -= this.stack.pop();
      $.each(this.dialogs, function(i) {
        if (i == cur_idx) {
          this.dialog('open');
          return false;
        }
      });
    }
  })
}) (jQuery);
