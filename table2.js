///~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~//
// Author     : Fhulu Lidzhade
// Date       : 17/06/2012   20:39
// Description:
//   table.js defines a jquery ui extension enhancing html tables. It adds featues 
//   such as:
//    1) sorting by field on column header click
//    2) paging with adjustable page size
//    3) customable row expansion/collapse when required
//    4) editable rows including select boxes
//    5) dynamic addition of rows
//    6) dynamic deletion of rows
//~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~~//

(function( $ ) {
  $.widget( "ui.table2", {
    options: {
      page_size: 0,
      sort: null,
      sort_order: 'asc',
    },
    
    _create: function()
    {
      console.log('creating table2')
      alert("Creating my table");
    }
  })
}) (jQuery);
