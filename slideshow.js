(function( $ ) {
  $.widget( "ui.slideshow", {
    options: {
      fadesAfter: 2500,
      changesEvery: 5000
    },

    _create: function() 
    {
      this.cur_idx = 0;
      this.container = this.element;
      this.slides = this.element.children();
      this.cur_slide = null;
    },
	
    fade: function()
    {
      var self = this;
    },

    start: function(start_idx)
    {
      this.slides.css('z-index', this.container.css('z-index')).hide();
      if (start_idx == undefined) start_idx = 0;
      this.cur_slide = this.slides.eq(start_idx);
      this.cur_slide.show();
      var self = this;
      this.interval_id = setInterval(function() {
        ++self.cur_idx;
        self.cur_idx %= self.slides.length;
        var next_slide = self.slides.eq(self.cur_idx);
        var zindex = self.cur_slide.css('z-index');
        next_slide.css('z-index', zindex-1).show();
        self.cur_slide.fadeOut(self.options.fadesAfter, function() {
          next_slide.css('z-index', zindex);
          self.cur_slide = next_slide;
        });
      }, this.options.changesEvery); 
    },
    
    stop: function()
    {
      if (this.interval_id == undefinded) return;
      clearInterval(this.interval_id);
    }
  });
}) (jQuery);
