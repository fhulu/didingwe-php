(function($) {
$.fn.mapper = function(options)
{
  if (options === undefined) options = {};
  options = $.extend({
     latitude: -33.884322,
     longitude: 18.632458,
     zoom: 8,
     pin: null,
     value: null
   }, options );
   
  
  options.zoom = parseInt(options.zoom);
  var obj = $(this);
  var map = {
    options: options,
    data:  {},
    _create: function() 
    {
      this.location(options.latitude, options.longitude);
    },
    
    show: function()
    {
      var props = {
          center: this.position,
          zoom: options.zoom,
          mapTypeId: google.maps.MapTypeId.ROADMAP
        };
      this.map = new google.maps.Map(document.getElementById(obj.attr('id')), props);
      this.map.setZoom(options.zoom);
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
      this.data.marker = new google.maps.Marker({
          map: this.map,
          position: this.position,
          animation: value
        });
    }
  }
  map._create();
  return map;
}
})(jQuery);