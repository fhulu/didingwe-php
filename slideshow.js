$.fn.showNextSlide = function(duration) {
  var slides = this.children('[slide]');
  var index = parseInt(this.attr('current_slide'));
  var nextIndex = (index + 1) % slides.length;
  this.attr('current_slide', nextIndex);
  slides.eq(index).slideToggle(duration, function() {
    slides.eq(nextIndex).show();
    slides.eq(index).hide();
  });
}
