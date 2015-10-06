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
      this.markers = {};
      this.hints = {};
      this.bouncing = null;
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

    _setOption: function( key, value ) {
      if (key === 'value')
        this.val(value);
    },

    addPoints: function(data)
    {
      var colors = this.options.colors;
      var hint = this.hint;
      var self = this;
      var map = this.map;
      var icon_path = this.options.icon_path;
      data.forEach(function(value) {
        var position = new google.maps.LatLng(parseFloat(value[1]), parseFloat(value[2]));
        var color = value[3];
        var icon = icon_path+colors[color]+'-dot.png';

        var id = value[0];
        var marker = new google.maps.Marker({
          position: position,
          map: self.map,
          icon: icon,
          id: id
        });
        self.markers[id] = marker;
        if (value.length < 4) return;
        self.hints[id] =  new google.maps.InfoWindow({content: value[4]});
        google.maps.event.addDomListener(marker, 'mouseover', function() {
          self.hints[marker.id].open(self.map, marker);
        });
        google.maps.event.addDomListener(marker, 'mouseout', function() {
          self.hints[marker.id].close();
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
      this.zoom(this.options.zoom);
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
      var position = $.isArray(value)? value: value.split(',');
      this.location(position[0], position[1]);
      return this;
    },

    toggleBounce: function(id, on, animation)
    {
      var marker = this.markers[id];
      if (!marker) return this;
      if (on===undefined) on = marker.getAnimation() != null;
      if (animation === undefined) animation = google.maps.Animation.BOUNCE;
      marker.setAnimation(on? animation: null);
      return this;
    },

    bounce: function(id)
    {
      this.bouncing = id;
      return this.toggleBounce(id, true);
    },

    bounceOff: function()
    {
      if (!this.bouncing) return this;
      return this.toggleBounce(this.bouncing, false);
    },

    center: function(id)
    {
      var marker = this.markers[id];
      if (marker)
        this.map.setCenter(marker.position);
      return this;
    },

    zoom: function(level)
    {
      this.map.setZoom(level);
      return this;
    }

  })
})(jQuery);
