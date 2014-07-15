$.fn.page = function(options, callback)
{
  if (options instanceof Function) {
    callback = options;
    options = {method: 'post'};
  } 

  var obj = $(this);
  var id = obj.attr('id');
  var selector = '#'+id+' *';
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
   
    expand_field: function(parent,data)
    {
      $.each(data, function(field, value) {
        if (typeof value === 'string') {
          data[field] = data[field].replace('$code', parent);
          $.each(data, function($field, $value) {
            if (typeof $value === 'string') 
              data[field] = data[field].replace('$'+$field, $value);
          });
        }
        else if ($.isPlainObject(value)) {
          data[field] = page.expand_field(field,value);
        }
      });
      return data;
    },
    
    expand_children: function(data)
    {
      if (data.children === undefined) return data.html;
  
      var html = "";
      $.each(data.children, function(field, value) {
        html += ' ' + value.html.replace('$children', page.expand_children(value));
      });
      return html;
    },
    
    read: function(data)
    {
      data = page.expand_field(id,data);
      console.log(data);
      data.html = data.html.replace('$children', page.expand_children(data));
      obj.replaceWith(data.html);
      return;
      if (data.children !== undefined)
        data.html = data.html.replace('$children', page.read_html(data.children));
      return data.html;
      if (data.type == 'wizard') {
        page.load_wizard(data);
        return this;
      }
      if (data.attributes == null) return this;
      var attr = page.attr = data.attributes;
      options.data_url = attr.data_url;
      var title = attr.title;
      if (attr.page !== undefined)
        document.title = attr.program + ' - ' + title;
      if (attr.class !== undefined) obj.addClass(attr.class);
      obj.addClass(attr.label_position);
      obj.append($('<p class=desc></p>').text(attr.desc));
      obj.append($('<span class=ajax_result></span>'));

      form.inputs.addClass(attr.fields_class);
      $.each(data.fields, form.add_field);
      $.each(data.actions, form.add_action);
      if (form.actions.children().length != 0) {
        form.inputs.append($('<p></p>'));
        form.inputs.append(form.actions);
      }
      if (form.inputs.children().length != 0) {
        obj.append(form.inputs);
        form.update_dates();
      }
      form.load_lists();
    },
    
    add_field: function(field, prop)
    {
      var label = $('<p></p>');
      if (form.attr.label_position !='inplace') {
        label.text(prop.name);
        if (prop.optional == 0) label.text('* ' + label.text());
        if (form.attr.label_suffix != '') label.text(label.text()+form.attr.label_suffix);
      }
      form.inputs.append(label);

      var anchor = $('<a></a>');
      var input;
      if (prop.input == "text" || prop.input == "password" || prop.input == 'file') {
        input = $('<input type='+prop.input+'></input>');
        if (form.attr.label_position == 'inplace')
          input.attr('placeholder', prop.name);
      }
      else if (prop.input == 'dropdown') {
        input = $('<select></select>');
        input.attr('default', '--Select '+prop.name+'--');
      }
      else if (prop.input == 'paragraph') {
        input = $('<textarea></textarea');
        input.height(prop.size);
      }
      else if (prop.input == 'date') {
        input = $('<input type=text></input>');
      }
      else {
        input = $("<label></label>");
      }
      
      if (prop.initial != null) input.val(prop.initial).text(prop.initial);
      
      input.attr(form.attr.method === 'post'?'name':'id', field);
      if (prop.enabled == 0) input.attr('disabled','disabled');
      anchor.append(input);
      anchor.append($('<span></span>').text(prop.desc));
      if (prop.visible == 0) {
        label.hide();
        anchor.hide();
      }
     
      form.inputs.append(anchor);
    },
    
    add_action: function(action, prop) 
    {
      if (prop.validator == 'validate') prop.validator = default_validator;
      if (prop.reference == 'validate') {
        prop.reference = default_validator;
        prop.method = 'check';
      }
      var input = prop.input == "button"? $('<button></button>'): $("<a></a>"); 
      if (prop.validator != null) 
        input.checkOnClick(selector, prop.validator);
      
      if (prop.method == "check") 
        input.checkOnClick(selector, prop.reference);
      else if (prop.method = 'link')
        input.click(function() { location.href = prop.reference; })
      
      input.attr('title',prop.desc);
      input.attr('id', action);
      input.text(prop.name);
      if (prop.visible == 0) input.hide();
      form.actions.append(input);
    },
  
    load_lists: function()
    {
      var lists = form.inputs.find('select');
      var no_of_lists = lists.length;
      var lists_loaded = 0;
      var form_id = form.id;
      lists.each(function() {
        var self = $(this);
        var field_id = self.attr('id');
        $.json('/?a=form/reference&form='+form_id+'&field='+field_id, function(result) {
          
          $.each(result, function(key, row) {
            self.append('<option f=t value="'+row.item_code+'">'+row.item_name+'</option>');
          });
          
          // handle default values
          var def = self.attr('default');
          if (def !== undefined) {
            var selected = self.find("[value='"+def+"']");
            if (selected.length == 0) 
              selected = $('<option>'+def+'</option>').prependTo(self);
            selected.prop('selected', true);
          }
          
          // run callback
          if (++lists_loaded == no_of_lists) form.options.done();
        });
      });

      if (no_of_lists == 0) form.options.done();
      
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
