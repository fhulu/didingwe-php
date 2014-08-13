/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */


(function( $ ) {
  $.widget( "ui.map", {
    options: {
      latitude: -33.884322,
      longitude: 18.632458,
      zoom: 8,
      pin: null,
      value: null,
      data: {}
    },
    
    _create: function() 
    {
      this.position = new google.maps.LatLng(this.options.latitude, this.options.longitude);
      var props = {
          center: this.position,
          zoom: this.zoom,
          mapTypeId: google.maps.MapTypeId.ROADMAP
        };
      this.map = new google.maps.Map(document.getElementById(this.element.attr('id')), props);
      this.map.setZoom(this.options.zoom);
      this.marker = new google.maps.Marker({
          map: this.map,
          position: this.position,
          animation: google.maps.Animation.Drop
        });

    }
  })
}) (jQuery);