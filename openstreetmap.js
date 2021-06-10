(function($) {
  $.widget("ui.mapper", {
    options: {
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

      this.show();
      console.log("options", this.options);

      if (this.options.center)
        this.location(this.options.center[0], this.options.center[1]);

      if (this.options.load) {
        var data = { action: 'data', path: this.options.path + '/load', key: this.options.key };
        $.json('/', {data: data}, function(data) {
          self.addPoints(data);
        });
      }
      var pos = this.options.position;
      if (pos)
        this.addPoint(pos);


      this.markOnClick();
    },

    markOnClick: function()
    {
      if (!this.options.marker.addOnClick) return this;
      var self = this;
      this.map.on('click', function(event) {
        var marker = self.markers['click'];
        if (marker != undefined && self.options.marker.toggleOnClick) marker.remove(self.map);
        var pos = [event.latlng.lat, event.latlng.lng];
        marker = self.addPoint({
          id: 'click',
          latitude: pos[0],
          longitude: pos[1],
          color: self.options.marker.default_color,
          hint: self.options.marker.hint
        });
        self.element.trigger('map_click', [pos, marker])
      });
    },

    _setOption: function( key, value ) {
      if (key === 'value')
        this.val(value);
    },

    addPoint: function(value)
    {
      if ($.isArray(value)) 
        value = { id: value[0], latitude: value[1], longitude: value[2], color: value[3], hint: value[4] };
      var colors = this.options.marker.colors;

      var icon_path = this.options.marker.path+colors[value.color]+'-dot.png';

      icon = L.icon($.extend({iconUrl: icon_path}, this.options.marker));
      var marker = L.marker(
        [parseFloat(value.latitude), parseFloat(value.longitude)],
        {icon: icon }
      );
      if (value.hint) {
        marker.bindPopup(value.hint)
         .on('mousemove', () => { marker.openPopup() });
      }
      marker.addTo(this.map);
      this.markers[value.id] = marker;

      return marker;
    },

    addPoints: function(data)
    {
      var self = this;
      data.forEach(function(value) {
        self.addPoint(value);
      });
    },

    show: function()
    {
      if (this.map) return;
      this.options.zoom = parseInt(this.options.zoom);
      if (!this.element.exists()) return;
      this.map = L.map(this.element[0].id).setView(this.options.center, this.options.zoom);
      L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token={accessToken}', {
        attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors, Imagery © <a href="https://www.mapbox.com/">Mapbox</a>',
        maxZoom: 18,
        id: 'mapbox/streets-v11',
        tileSize: 512,
        zoomOffset: -1,
        accessToken: this.options.access_token
      }).addTo(this.map);
    },

    location: function(latitude, longitude, show)
    {
      this.options.center = [latitude, longitude];
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
      // if (on===undefined) on = marker.getAnimation() != null;
      // if (animation === undefined) animation = google.maps.Animation.BOUNCE;
      // marker.setAnimation(on? animation: null);
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
