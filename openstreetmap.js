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


      if (this.options.markOnClick) this.markOnClick();
    },

    markOnClick: function()
    {
      var self = this;
      this.map.addListener('click', function(event) {
        if (self.options.markOnClick == 'toggle') {
          var marker = self.markers['click'];
          if (marker != undefined) marker.setMap(null);
        }
        var pos = event.latLng
        self.markers['click'] = new google.maps.Marker({
          position: event.latLng,
          map: self.map
        });
        self.element.trigger('map_clicked', [pos])
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
      var colors = this.options.colors;

      var icon_path = this.options.icon_path+colors[value.color]+'-dot.png';

      icon = L.icon($.extend({iconUrl: icon_path}, this.options));
      var marker = L.marker(
        [parseFloat(value.latitude), parseFloat(value.longitude)],
        {icon: icon }
      )
      .bindPopup(value.hint);
      marker.on('mousemove', () => { marker.openPopup() });
      marker.addTo(this.map);
      this.markers[value.id] = marker;
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
      var props = {
        center: this.position,
        zoom: this.options.zoom,
        //mapTypeId: google.maps.MapTypeId.ROADMAP
      };
     // this.map = new google.maps.Map(this.element[0], props);
      console.log("map centre", this.options.center);
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
