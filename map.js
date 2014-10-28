(function($) {
  $.widget("ui.mapper", {
    options: {
      latitude: -29.801842,
      longitude: 30.592950,
      zoom: 8,
      pin: null,
      value: null,
      marker: null,
    },
    _create: function()
    {
      var self = this;
      this.element.on('customValue', function(event, value) {
        self.val(value);
      })
      this.show();
    },
    show: function()
    {
      this.options.zoom = parseInt(this.options.zoom);
      var props = {
        center: this.position,
        zoom: this.options.zoom,
        mapTypeId: google.maps.MapTypeId.ROADMAP
      };
      this.map = new google.maps.Map(document.getElementById(this.element.attr('id')), props);
      this.map.setZoom(this.options.zoom);
      this.marker(google.maps.Animation.DROP);
    },
    location: function(latitude, longitude, show)
    {
      this.position = new google.maps.LatLng(latitude, longitude);
      if (show === undefined || show === true)
        this.show();
    },
    val: function(value)
    {
      if (value === undefined) {
        return join([this.position.lat(), this.position.lng()]);
      }
      var position = value.split(',');
      this.location(position[0], position[1]);
      return this;
    },
    marker: function(value)
    {
      if (value === undefined)
        return this.marker;
        this.options.marker = new google.maps.Marker({
        map: this.map,
        position: this.position,
        animation: value
      });
    },
  })
})(jQuery);