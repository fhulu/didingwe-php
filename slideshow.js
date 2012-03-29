(function( $ ) {
  $.widget( "ui.slideshow", {
    _create: function() 
    {
      this.cur_idx = 0;
      this.slides = this.element.children();
      this.cur_slide = null;
      this.slides.hide().css('z-index', 100);
    },

    slideshow: function(selector) 
    { 
      return $(this).find(selector); 
    },
    
    start: function(fade_interval, show_interval, start_idx)
    {
      if (start_idx == undefined) start_idx = 0;
      this.cur_slide = this.slides.eq(start_idx);
      var self = this;
      this.interval_id = setInterval(function() {
        ++self.cur_idx;
        self.cur_idx %= self.slides.length;
        var next_slide = self.slides.eq(self.cur_idx);
        var zindex = self.cur_slide.css('z-index');
        next_slide.css('z-index', zindex-1).show();
        self.cur_slide.fadeOut(fade_interval, function() {
          next_slide.css('z-index', zindex);
          self.cur_slide = next_slide;
        });
      }, show_interval); 
    },
    
    stop: function()
    {
      if (this.interval_id == undefinded) return;
      clearInterval(this.interval_id);
    }
  });
}) (jQuery);
