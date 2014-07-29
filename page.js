$.fn.page = function(options, callback)
{
  if (options instanceof Function) {
    callback = options;
    options = {method: 'post'};
  } 

  var obj = $(this);
  var id = obj.attr('id');
  var parent_id = obj.parent().attr('id');
  if (parent_id === undefined) parent_id = '';
  var selector = '#'+id;
  options = $.extend({
    method: 'post',
    url: '/?a=page/read',
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
      $.json(url, { data: {page: parent_id, field: id} }, this.read);
    },
   
    expand_value: function(values,value)
    {
      $.each(values, function(code, subst) {
        if (typeof subst === 'string') 
          value = value.replace('$'+code, subst);
      });
      return value;
    },
    
    expand_fields: function(parent_id, data, values)
    {
      if (values === undefined) values = {};
      $.extend(values, data);
      $.each(data, function(field, value) {
        if (typeof value === 'string' && field !== 'template' && field != 'attr') {
          value = value.replace('$code', parent_id);
          data[field] = page.expand_value(values, value);
          $.extend(values, data);
        }
        else if ($.isPlainObject(value)) {
          data[field] = page.expand_fields(field, value, values);
        }
      });
      return data;
    },
    
    expand_attr: function(html, values, attr)
    {
      if (attr === undefined) return html;
      attr = page.expand_value(values, attr);
      return html.replace(/^<(\w+) ?/,'<$1 '+attr + ' ');
    },
    
    expand_objects: function(objects, orig_values)
    {
      if (orig_values === undefined) orig_values = {};
      
      $.extend(orig_values, objects);

      $.each(objects, function(field, data) {
        if (!$.isPlainObject(data) && !$.isArray(data)) return;
        var values = $.isArray(data)? orig_values: $.extend({}, orig_values, data);
        var my_html = "";
        $.each(data, function(f, object) {
          if (object === null || f === 'template') return;
          if (!$.isPlainObject(object) && !$.isArray(object)) return;
          $.extend(values, object);
          page.expand_objects(object, values);
          var val = object.html;
          val = page.expand_attr(val, values, object.attr);
          if (objects.template !== undefined) {
            val = page.expand_attr(val, values, values.attr);
            var template  = page.expand_value(values, objects.template);
            template = template.replace('$code', f);
            val = template.replace('$field',val);
          }
          if (obj.has_data !== undefined) {
            val = val.replace(/^<(\w+) ?/,'<$1 has_data ');
          }

          if (object.children === undefined && object.has_data === undefined) {
           // console.log(f, object);
//            val = val.replace('$children', '');
          }
          object.html = val;
          my_html += object.html;
        });
        if (my_html == '') my_html = data.html;
        if (objects.html !== undefined) 
          objects.html = objects.html.replace('$'+field, my_html);
      });
    },
    
    inherit_values: function(parent, child)
    {
      $.each(parent, function(key, value) {
        if (typeof value !== "string") return;
        if (key === "html" || key === 'code' || key === 'template') return;
        if (child[key] !== undefined) return;
        child[key] = value;
      });
    },
    
    expand_children: function(object, template)
    {
      if (!$.isPlainObject(object) && !$.isArray(object)) return;
      var children ="";
      $.each(object, function(field, child) {
        if (!$.isPlainObject(child) && !$.isArray(child) || child === null) return;
        page.inherit_values(object, child);
        page.expand_children(child, object.template);
        if (template !== undefined) {
          var expanded = page.expand_value(child, template);
          expanded = expanded.replace('$code', field);
          child.html = expanded.replace('$field', child.html);
        }
        if (object.html === undefined) 
          children += child.html;
        else
          object.html = object.html.replace('$'+field, child.html);
      });
      if (object.html === undefined) 
        object.html = children;
    },
    
    read: function(data)
    {
      data = page.expand_fields(id,data);
      page.expand_children(data);
      $(selector).replaceWith($(data.html));
      page.set_data(data,$(selector).parent(), function() {
        page.assign_handlers(data);
      });
    },
    
    get_config: function(config, domid)
    {
      var found;
      $.each(config.children, function(id, obj) {
        if (id === domid) {
          found = obj;
          return;
        }
      });
      if (found) return found;
      
      $.each(config.children, function(id, obj) {
        var found = page.get_config(obj, domid);
        if (found !== null) return found;
      });
      return null;
    },
    
    set_data: function(data, parent,callback) 
    {
      var count = parent.children('[has_data]').length;
      var done = 0;
      parent.children().each(function() {
        var child = $(this);
        page.set_data(data,child, function() {
          var has_data = child.attr('has_data');
          if (has_data === undefined) return;
          child.removeAttr('has_data');
          var field_id = child.attr('id');
          var params = {page: id, field:field_id, data: has_data };
          $.json('/?a=page/data', {data:params}, function(result) {
            result.html = child.html();
            page.expand_children(result);
            child.html(result.html);
            if (++done === count) page.set_data(data, parent, callback);
          });
        });
      });
      if (count===0 && callback !== undefined) callback();
    },

    assign_handlers: function(data)
    {
      if (data.children === undefined) return;
      $.each(data.children, function(id, child) {
        var obj = $('#'+id);
        if (child.check !== undefined) 
          obj.checkOnClick(selector + ' ' + child.check, child.url);
        else if (child.url !== undefined)
          obj.click(function() { location.href = child.url; });
        page.assign_handlers(child);
      });
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
  if (options.autoLoad) page.load();
  return page;
}



$.fn.formLoaded = function(callback)
{
  $(this).attr('loaded', callback);
}
