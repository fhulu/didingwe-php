$.fn.form = function(options)
{
  var obj = $(this);
  var id = obj.attr('id');
  var selector = '#'+id+' *';
  options = $.extend({
    method: 'post',
    url: '/?a=form/load&code='+id,
    error: undefined,
    attr: null,
    autoLoad: true,
    success: function() {}
  }, options);
  
  options.done = function() {
    var func = $(this).attr('loaded');
    if (func !== undefined) func();
    options.success();
  }
  var form = {
    object: obj,
    form: this,
    inputs: $('<div></div>'),
    actions: $('<div class=actions></div>'),
    options: options,
    data: null,
    load: function(url) 
    {
      if (url === undefined) url = options.url;
      $.json(url, this.read);
    },
   
    read: function(data) 
    {
      form.data = data;
      if (data.forms != null) {
        form.load_wizard(data);
        return this;
      }
      if (data.attributes == null) return this;
      form.attr = data.attributes;
      var attr = form.attr;
      var title = attr.title;
      document.title = attr.program + ' - ' + title;
      obj.append($('<p class=title></p>').text(title));
      obj.addClass(attr.class);
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
      if (prop.input == "text" || prop.input == "password") {
        input = $('<input type='+prop.input+'></input>');
        if (form.attr.label_position == 'inplace')
          input.attr('placeholder', prop.name);
      }
      else if (prop.input == 'dropdown') {
        input = $('<select></select>');
        input.attr('list', prop.reference);
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
      var input;
      if (prop.input == "button") {
        input = $('<button></button>'); 
        if (prop.method == "check")
          input.checkOnClick(selector, prop.reference);
        else if (prop.method = 'link')
          input.click(function() { location.href = prop.reference; })
      }
      else if (prop.input == 'link')
        input = $("<a href='"+prop.reference+"'></a>");
      input.attr('title',prop.desc);
      input.attr('id', action);
      input.text(prop.name);
      if (prop.visible == 0) input.hide();
      form.actions.append(input);
    },
  
    load_lists: function()
    {
      var lists = form.inputs.find('select[list]');
      var lists_loaded = 0;
      var index = location.href.indexOf("&");
      var key = index >= 0? location.href.substr(index+1): '';
      var url = form.attr.data_url != null? form.attr.data_url+key: null;
      lists.jsonLoadOptions(function() {
        if (++lists_loaded == lists.length) {
          if (url != null) obj.loadChildren(url);
          form.options.done();
        }
      });
      if (lists.length == 0) {
        if (url != null) obj.loadChildren(url);
        form.options.done();
      }
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
        if (index > 1 && form.show_back == 1) div.attr('back','');
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
        }});
      });
    }
  };
  if (options.autoLoad) form.load();
  return form;
}



$.fn.formLoaded = function(callback)
{
  $(this).attr('loaded', callback);
}
