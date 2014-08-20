(function( $ ) {
  $.widget( "ui.mapper", {
  options: {
     latitude: -33.884322,
     longitude: 18.632458,
     zoom: 8,
     pin: null,
     value: null,
     marker: null,
   },
     
    _create: function() 
    {
      this.location(this.options.latitude, this.options.longitude);
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
      if (show === undefined || show === true) this.show();
    },
    
    val: function(value)
    {
      if (value === undefined) {
        return join([this.position.lat(), this.position.lng()]);
      }
      var position = value.split(',');
      this.location(position[0], position[1]);
    },
    
    marker: function(value)
    {
      if (value === undefined) return this.marker;
      this.options.marker = new google.maps.Marker({
          map: this.map,
          position: this.position,
          animation: value
        });
    }
  })
})(jQuery);