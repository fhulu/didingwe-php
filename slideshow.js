$.fn.slideshow = function(options) {
  console.log("creating slideshow", options);
  var slides = this.children('[slide]');
  slides.eq(options.start_slide).show();
  this.data('effects', options.effects);
  this.data('effect', -1);
}

$.fn.showNextSlide = function(duration) {
  console.log("slide duration", duration);
  var slides = this.children('[slide]');
  var index = parseInt(this.attr('current_slide'));
  var nextIndex = (index + 1) % slides.length;
  this.attr('current_slide', nextIndex);
  var zIndex = parseInt(this.attr('zIndex'));
  var current = slides.eq(index).zIndex(zIndex+1);
  slides.eq(nextIndex).show();
  var effect = current.attr('effect');
  if (!effect) {
    var effects = this.data('effects');
    index = (this.data('effect')+1) % effects.length;
    this.data('effect', index);
    effect = effects[index];
  }

  current.toggle(effect, duration, "linear", function() {
    current.zIndex(zIndex);
  });
}
