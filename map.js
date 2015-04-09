(function($) {
  $.widget("ui.mapper", {
    options: {
      latitude: -29.801842,
      longitude: 30.592950,
      zoom: 8,
      pin: null,
      value: null,
      marker: null,
      hint: null
    },
    _create: function()
    {
      var self = this;
      this.element.on('customValue', function(event, value) {
        self.val(value);
      });
      if (this.options.center)
        this.location(this.options.center[0], this.options.center[1]);
      
      if (this.options.load) {
        var data = { action: 'data', path: this.options.path + '/load' };
        $.json('/', {data: data}, function(data) {
          self.addPoints(data);
        });
      }
      this.show();
    },
    
    addPoints: function(data)
    {
      var colors = this.options.colors;
      var hint = this.hint;
      var self = this;
      var map = this.map;
      var icon_path = this.options.icon_path;
      data.forEach(function(value) {
        var position = new google.maps.LatLng(parseFloat(value[0]), parseFloat(value[1]));
        var color = value[2];
        var icon = icon_path+colors[color]+'-dot.png';
        var marker = new google.maps.Marker({
          position: position,
          map: self.map,
          icon: icon
        });
        google.maps.event.addDomListener(marker, 'mouseover', function() {
          hint = new google.maps.InfoWindow({content: value[3]});
          hint.open(self.map, marker);
        });
        google.maps.event.addDomListener(marker, 'mouseout', function() {
          if (hint !== null)
            hint.close();
        });
      });
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
