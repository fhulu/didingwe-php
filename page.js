$.fn.page = function(options, callback)
{
  if (options instanceof Function) {
    callback = options;
    options = {method: 'post'};
  } 

  var obj = $(this);
  var id = obj.attr('id');
  var selector = '#'+id;
  options = $.extend({
    method: 'post',
    url: '/?a=page/read&code='+id,
    error: undefined,
    autoLoad: true
  }, options);
  
  var page  = {
    object: obj,
    options: options,
    data: null,
    id: id,
    load: function(url) 
    {
      if (url === undefined) url = options.url;
      $.json(url, this.read);
    },
   
    expand_value: function(values,value)
    {
      $.each(values, function(code, subst) {
        if (typeof subst === 'string') 
          value = value.replace('$'+code, subst);
      });
      return value;
    },
    
    expand_fields: function(parent_name, data, values)
    {
      $.extend(values, data);
      $.each(data, function(field, value) {
        if (typeof value === 'string' && value.indexOf('$') >= 0 && field !== 'template' && field != 'attr') {
          value = value.replace('$code', parent_name);
          data[field] = page.expand_value(values, value);
          $.extend(values, data);
        }
        else if ($.isPlainObject(value)) {
          data[field] = page.expand_fields(field, value, values);
        }
      });
      return data;
    },
    
    expand_children: function(data, values)
    {
      if (data.children === undefined) return data.html;
  
      var html = "";
      $.extend(values, data);
      $.each(data.children, function(f, object) {
        var val = object.html;
        $.extend(values, object);
        if (data.template !== undefined) {
          if (data.attr !== undefined) {
            var attr = page.expand_value(values, data.attr);
            val = val.replace(/^<(\w+) ?/,'<$1 '+attr);
          }
          var template  = page.expand_value(values, data.template);
          val = template.replace('$field',val);
        }
        if (obj.has_data !== undefined) {
          val = val.replace(/^<(\w+) ?/,'<$1 has_data');
        }
        html += ' ' + val.replace('$children', page.expand_children(object, values));
      });
      return html;
    },
    
    read: function(data)
    {
      data = page.expand_fields(id,data,{});
      data.html = data.html.replace('$children', page.expand_children(data, {}));
      $(selector).replaceWith($(data.html));
      page.set_data(data,$(selector));
    },
    
     expand_values: function(data,values)
    {
      if (data.children === undefined) return data.html;
  
      var html = "";
      $.extend(values, data);
      $.each(data.children, function(f, object) {
        var val = object.html;
        $.extend(values, object);
        if (data.template !== undefined) {
          if (data.attr !== undefined) {
            var attr = page.expand_value(values, data.attr);
            val = val.replace(/^<(\w+) ?/,'<$1 '+attr);
          }
          var template  = page.expand_value(values, data.template);
          val = template.replace('$field',val);
        }
        if (obj.has_data !== undefined) {
          val = val.replace(/^<(\w+) ?/,'<$1 has_data');
        }
        val = val.replace('$children', page.expand_children(object, values));
        if (val.indexof('$children')<0) val = val.replace('$children', '');
        html += ' ' + val;
      });
      return html;
    },
   
    set_data: function(data, parent, callback) 
    {
      var count = parent.children('[has_data]').length;
      var done = 0;
      parent.children().each(function() {
        var child = $(this);
        page.set_data(child, function() {
          if (child.attr('has_data') !== undefined) {
            var data = {page: id, field:child.attr('id')};
            child.json('/?a=page/data', {data:data}, function(result) {
              //page.expand_values(child,result)
              if (++done === count && callback !== undefined) callback();
            });
          }
        });
      });
      if (count===0 && callback !== undefined) callback();
    },

    update_dates: function()
    {
      // load date picker after form is loaded 
      // bug on date picker that doesn't allow it to be loaded when form has not completed loading 
      obj.find('.datepicker').datepicker();
      $.each(form.data.fields, function(field, prop) {
        if (prop.input != 'date') return;
        var input = $('#'+field);
        if (prop.reference == null) {
          input.datepicker();
          return;
        }
        var params = prop.reference.split(',');
        if (params.length == 1) 
          input.datepicker({range: params[0]});
        else if (params.length == 2) 
          input.datepicker({range: params[0], beforeShowDay: $.datepicker[params[1]]});
      });
    },
    
    load_wizard: function(data)
    {
      var index = 0;
      var done = 0;
      var parent = form;
      $.each(data.forms, function(id, form) {
        var div = $('<div></div>');
        div.attr('caption', form.title);
        div.attr('id', id);
        if (index > 0 && form.show_back == 1) div.attr('back','');
        if (++index < data.size && form.show_next == 1) div.attr('next','');
        obj.append(div); //todo: order may be broken if an earlier form takes longer than a later one
        div.form({success: function() {
          if (++done != data.size) return;

          obj.pageWizard({title: data.program} );
          $.each(data.forms, function(id, form) {
            if (form.next_action != null) 
              $('.'+id+'_next').checkOnClick(selector+' *', form.next_action);
          });
          $(selector+ ' .title').hide();
          parent.options.success();
        }});
      });
    }
  };
  page.load();
  return page;
}



$.fn.formLoaded = function(callback)
{
  $(this).attr('loaded', callback);
}
