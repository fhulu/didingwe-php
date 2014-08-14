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
    creation: null,
    links: "",
    load: function(url) 
    {
      if (url === undefined) url = options.url;
      var data = $.extend({page:id}, options.data);
      $.json(url, { data: data }, this.read);
    },
   
    expand_value: function(values,value)
    {
      $.each(values, function(code, subst) {
        if (typeof subst === 'string') {
          value = value.replace('$'+code, subst);
          if (value.indexOf('$') < 0) return;
        }
      });
      return value;
    },
    
    expand_fields: function(parent_id, data)
    {
      $.each(data, function(field, value) {
        if (typeof value === 'string' && value.indexOf('$') >= 0 && field !== 'template' && field != 'attr') {
          value = value.replace('$code', parent_id);
          data[field] = page.expand_value(data, value);
        }
        else if ($.isPlainObject(value)) {
          page.inherit_values(data, value);
          data[field] = page.expand_fields(field, value);
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
    
    inherit_values: function(parent, child)
    {
      $.each(parent, function(key, value) {
        if (typeof value !== "string") return;
        if (key === "html" || key === 'code' || key === 'template') return;
        if (child[key] !== undefined) return;
        child[key] = value;
      });
    },
    
    expand_template: function(field, child, object)
    {
      if (object.template === undefined) return false;
      var expanded = page.expand_value(child, object.template);
      expanded = expanded.replace('$code', field);
      if (child.html === undefined) {
        if (expanded.indexOf('$field') >= 0) return false;
        child.html = expanded;
      }
      else {
        child.html = page.expand_attr(child.html, child, object.attr);
        child.html = expanded.replace('$field', child.html);
      }
      return true;
    },
    
    expand_children: function(object)
    {
      if (!$.isPlainObject(object) && !$.isArray(object)) return;
      if (object.html === undefined) {
        object.html = "";
        if (object.template === undefined) object.template = "$field";
      }
      $.each(object, function(field, child) {
        if (!$.isPlainObject(child) && !$.isArray(child) || child === null) return;
        page.inherit_values(object, child);
        if (child.html !== undefined)
          child.html = page.expand_attr(child.html, child, child.attr);
        var expanded = page.expand_template(field, child, object);
        page.expand_children(child); 
        if (!expanded)
          page.expand_template(field, child, object);
        object.html = object.html.replace('$'+field, child.html);
        if (object.template !== undefined)
          object.html += child.html;
        object.html = page.expand_value(object, object.html);
      });
      if (object.css !== undefined) {
        if (object.css === null) object.css = object.code + ".css";
        var ref=document.createElement("link")
        ref.setAttribute("rel", "stylesheet");
        ref.setAttribute("type", "text/css");
        ref.setAttribute("href", object.css);        
        document.getElementsByTagName("head")[0].appendChild(ref);
      }
    },

    custom_create: function(object)
    {
      $.each(object, function(f, child) {
        if ($.isPlainObject(child)) page.custom_create(child);
      });
      
      if (object.create !== undefined) {
        var create = object.create;
        object.create = undefined;
        console.log(object.code, create);
        object.creation = $('#'+object.code)[create](object).data(create);
      }      
    },
    
    load_link: function(link,type, callback)
    {
      if (page.links.indexOf("["+link+"]")>=0) return;

      if (type == 'css') {
        $('<link>').attr('ref','stylesheet')
                .attr('type','text/css')
                .attr('href', link)
                .appendTo('head');
      }
      else if (type === 'script') {
        var script = $('<script></script>').attr('type','text/javascript').attr('src', link);
        if (callback !== undefined) {
          script.onreadystatechange = callback;
          script.onload = callback;
        }
        script.appendTo('head');
      }
      page.links +="["+link+"]"
    },
    
    load_links: function(object, type, callback)
    {
      if (object[type] !== undefined) {
        var links = object[type].split(',');
        object[type] = undefined;
        var loaded = 0;
        $.each(links, function(i, link) {
          page.load_link(link,type, function() {
            console.log('loading', link, loaded)
            if (++loaded == links.length && callback !== undefined)
              callback();
          });
        });
      }      
      $.each(object, function(f, child) {
        if ($.isPlainObject(child)) page.load_links(child, type);
      });      
    },
    
    read: function(data)
    {
      data = page.expand_fields(id,data);
      page.expand_children(data);
      var html = $(data.html);
      
      obj.html(html.html());
      obj.on('loaded', function() {
        page.load_links(data,'css');
        page.load_links(data,'script', function() {
          //page.custom_create(data);
        });
        page.custom_create(data);
        page.assign_handlers(data);
        obj.trigger('read', [data]);
      });
      page.load_data(obj.parent());
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
    
    load_data: function(parent) 
    {

      parent.find('*').each(function() {
        var object = $(this);
        if (object.attr('has_data') === undefined) {
          object.trigger('loaded');
          return;
        }

        object.removeAttr('has_data');
        var field_id = object.attr('id');
        var params = {_page: id, _field:field_id};
        $.json('/?a=page/data', {data:params}, function(result) {
          result.html = object.html();
          page.expand_children(result);
          object.html(result.html);
          object.trigger('loaded', result);
        });
      });
    },

    assign_handlers: function(object)
    {
      $.each(object, function(field, child) {
        if (!$.isPlainObject(child)) return;
        var obj = $('#'+field);
        var data = {_page: id, _field: field };
        if (child.validate !== undefined || child.action !== undefined && obj.attr('action')===undefined)  {
          obj.attr('action','');
          child.action = child.validate = undefined;
          var selector = child.selector;
          if (selector !== undefined) {
            selector = selector.replace(/(^|[^\w]+)page([^\w]+)/,"$1"+id+"$2");
            obj.checkOnClick(selector, '/?a=page/action', {data: data }, function(result) {
              obj.trigger('processed', [result]);
            });
          }
          else obj.click(function() {
            $.json('/?a=page/action', {data: data}, function(result) {
              obj.trigger('processed', [result]);
            });
          });
        }
        else if (child.url !== undefined) {
          obj.click(function() { location.href = child.url; });
        }
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
