$.fn.showNextSlide = function(duration) {
  var index = parseInt(this.attr('current_slide'));
  var slides = this.children('[slide]');
  slides.eq(index).slideToggle(duration, function() {
    slides.eq(++index % slides.length).show();
  });
}
